<?php

namespace iutbay\yii2\mm\models;

use Yii;
use yii\helpers\FileHelper;
use yii\imagine\Image;
use Imagine\Image\ManipulatorInterface;

class Thumb extends \yii\base\Model
{

    const SIZE_THUMB = 'thumb';
    const SIZE_MEDIUM = 'medium';
    const SIZE_LARGE = 'large';
    const SIZE_FULL = 'full';

    public $path;
    public $srcPath;
    public $dstPath;
    public $size;
    public $extension;
    public $type;
    public $realPath;

    public $resizeMode = ManipulatorInterface::THUMBNAIL_INSET;

    public static $extensions = [
        'jpg' => 'jpeg',
        'jpeg' => 'jpeg',
        'png' => 'png',
        'gif' => 'gif',
        'bmp' => 'bmp',
    ];

    public static $sizes = [
        self::SIZE_THUMB => [150, 150],
        self::SIZE_MEDIUM => [300, 300],
        self::SIZE_LARGE => [600, 600],
    ];

    /**
     * @var \iutbay\yii2\mm\components\FileSystem
     */
    public $fs;

    public $thumbsPath;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['path', 'validatePath'],
            ['size', 'validateSize'],
        ];
    }

    /**
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validatePath($attribute, $params)
    {
        $this->$attribute = FileHelper::normalizePath($this->$attribute, '/');

        $info = $this->getPathInfo($this->$attribute);
        if (is_array($info) && $this->fs->has($info['srcPath'])) {
            $this->setAttributes($info, false);
        } else {
            $this->addError($attribute, 'Invalid path.');
        }
    }

    /**
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validateSize($attribute, $params)
    {
        if ($this->$attribute === self::SIZE_FULL)
            return;

        if (is_array($this->$attribute)) {
            foreach (self::$sizes as $size) {
                if ($this->$attribute === $size)
                    return;
            }
            $this->addError($attribute, 'Invalid size.');
        }
    }

    /**
     * Get info from path
     * @param string $path
     * @return null|array
     */
    private function getPathInfo($path)
    {
        $regexp = '#^(.*)\.(' . self::getExtensionsRegexp() . ')$#';
        if (preg_match($regexp, $path, $matches)) {
            $name = $matches[1];
            $extension = $matches[2];
            $size = self::SIZE_FULL;
            if (preg_match('#^(.*)_([0-9]+)x([0-9]+)$#', $name, $matches)) {
                $name = $matches[1];
                $size = [(int)$matches[2], (int)$matches[3]];
            }
            return [
                'srcPath' => $name . '.' . $extension,
                'dstPath' => $path,
                'size' => $size,
                'extension' => $extension,
                'type' => self::$extensions[$extension],
            ];
        }
        return false;
    }

    /**
     * 
     */
    public function save()
    {
        $path = Yii::getAlias($this->thumbsPath . '/' . $this->dstPath);
        $stream = $this->fs->readStream($this->srcPath);

        if (!is_resource($stream))
            return false;

        if ($this->size === self::SIZE_FULL) {
            file_put_contents($path, $stream);
            fclose($stream);
        } else {
            $image = Image::thumbnail($stream, $this->size[0], $this->size[1], $this->resizeMode);
            fclose($stream);
            if (!$image || !$image->save($path)) {
                return false;
            }
        }
        $this->realPath = $path;
        return true;
    }

    /**
     * Get extensions regexp
     * @return string regexp
     */
    public static function getExtensionsRegexp()
    {
        $keys = array_keys(self::$extensions);
        return '(?i)' . join('|', $keys);
    }

}