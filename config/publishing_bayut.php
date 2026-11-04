<?php

// ربط حقول Feed بيوت (مؤقت لحد توثيقهم الرسمي): اسم عنصر XML => مفتاح من ListingPayload
return [
    'root'           => 'listings',
    'item'           => 'listing',
    'images_wrapper' => 'images',
    'image_item'     => 'image',
    'fields' => [
        'reference'         => 'code',
        'title'             => 'title',
        'description'       => 'description_text',
        'purpose'           => 'purpose',
        'type'              => 'type',
        'price'             => 'price',
        'area'              => 'area',
        'city'              => 'city',
        'neighborhood'      => 'neighborhood',
        'latitude'          => 'latitude',
        'longitude'         => 'longitude',
        'bedrooms'          => 'rooms',
        'bathrooms'         => 'bathrooms',
        'ad_license_number' => 'ad_license_number',
        'ad_license_expiry' => 'ad_license_expiry',
        'fal_license_number'=> 'fal_license_number',
    ],
];
