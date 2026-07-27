<?php

return [
    'libreoffice_path' => env('LIBRE_OFFICE_PATH', ''),
    'conversion_timeout' => env('DOCX_CONVERSION_TIMEOUT', 120),
    'pdfunite_path' => env('PDFUNITE_PATH', ''),
    'temp_dir' => storage_path('app/temp/libreoffice'),
];
