<?php

namespace FrontCart\Service;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Action\Image;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ConfigQuery;

class ImageService
{
    protected $eventDispatcher;

    public function __construct(EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getImageData($image, $type)
    {
        if (null !== $image) {
            try {
                $imageEvent = self::createImageEvent($image->getFile(), $type);
                $imageEvent->setWidth(200)
                    ->setHeight(200)
                    ->setResizeMode(Image::EXACT_RATIO_WITH_BORDERS);

                $this->eventDispatcher->dispatch(TheliaEvents::IMAGE_PROCESS, $imageEvent);

                return $imageEvent->getFileUrl();

            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }
    }

    protected function createImageEvent($imageFile, $type)
    {
        $imageEvent = new ImageEvent();
        $baseSourceFilePath = ConfigQuery::read('images_library_path');
        if ($baseSourceFilePath === null) {
            $baseSourceFilePath = THELIA_LOCAL_DIR . 'media' . DS . 'images';
        } else {
            $baseSourceFilePath = THELIA_ROOT . $baseSourceFilePath;
        }
        // Put source image file path
        $sourceFilePath = sprintf(
            '%s/%s/%s',
            $baseSourceFilePath,
            $type,
            $imageFile
        );
        $imageEvent->setSourceFilepath($sourceFilePath);
        $imageEvent->setCacheSubdirectory($type);

        return $imageEvent;
    }
}

