<?php

/*
 * ! WARNING !
 *
 * This file is auto-generated.
 * Please don't modify it by-hand or all your changes can be lost.
 */

return[
    'modules' => [
        'image' => [
            'defaultThumbnailSize' => '80x80',
            'noImageSrc' => 'http://placehold.it/300&text=Image+not+found',
            'thumbnailsDirectory' => 'thumbnail',
            'useWatermark' => '0',
            'watermarkDirectory' => 'watermark',
        ],
    ],
    'components' => [
        'fs' => [
            'class' => 'creocoder\\flysystem\\LocalFilesystem',
            'path' => '@webroot/files',
        ],
    ],
];
