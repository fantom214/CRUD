<?php

namespace Backpack\CRUD\Tests\config\Models;

use Illuminate\Database\Eloquent\Model;

class FakeUploader extends Uploader
{

    protected $table = 'uploaders';
    
    protected $casts = [
        'extras' => 'array',
    ];

    protected $fakeColumns = ['extras'];
}
