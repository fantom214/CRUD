<?php

namespace Backpack\CRUD\Tests\Unit\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Uploaders\SingleBase64Image;
use Backpack\CRUD\app\Library\Uploaders\SingleFile;
use Backpack\CRUD\app\Library\Uploaders\Support\Interfaces\UploaderInterface;
use Backpack\CRUD\Tests\config\CrudPanel\BaseCrudPanel;
use PHPUnit\Framework\Attributes\Group;

class UploadersInternalsTest extends BaseCrudPanel
{
    protected $uploaderRepository;

    public function setUp(): void
    {
        parent::setUp();
        $this->uploaderRepository = $this->app->make('UploadersRepository');
    }

    public function test_it_registers_default_uploaders()
    {
        $this->assertTrue($this->uploaderRepository->hasUploadFor('image', 'withFiles'));
        $this->assertTrue($this->uploaderRepository->hasUploadFor('upload', 'withFiles'));
        $this->assertTrue($this->uploaderRepository->hasUploadFor('upload_multiple', 'withFiles'));

        $this->assertFalse($this->uploaderRepository->hasUploadFor('dropzone', 'withFiles'));
    }

    public function test_it_registers_default_uploaders_classes()
    {
        $this->assertTrue(is_a($this->uploaderRepository->getUploadFor('image', 'withFiles'), UploaderInterface::class, true));
        $this->assertTrue(is_a($this->uploaderRepository->getUploadFor('upload', 'withFiles'), UploaderInterface::class, true));
        $this->assertTrue(is_a($this->uploaderRepository->getUploadFor('upload_multiple', 'withFiles'), UploaderInterface::class, true));
    }

    #[Group('fail')]
    public function test_it_throws_exception_if_uploader_or_group_is_not_registered()
    {
        $this->expectException(\Exception::class);

        $this->uploaderRepository->getUploadFor('dropzone', 'withFiles');
    }

    #[Group('fail')]
    public function test_it_can_add_more_uploaders()
    {
        $this->uploaderRepository->addUploaderClasses([
            'dropzone' => SingleFile::class,
        ], 'withFiles');

        $this->assertTrue($this->uploaderRepository->hasUploadFor('dropzone', 'withFiles'));
        $this->assertTrue(is_a($this->uploaderRepository->getUploadFor('dropzone', 'withFiles'), UploaderInterface::class, true));
    }

    #[Group('fail')]
    public function test_it_validates_uploaders_when_adding()
    {
        $this->expectException(\Exception::class);

        $this->uploaderRepository->addUploaderClasses([
            'dropzone' => 'InvalidClass',
        ], 'withFiles');
    }

    #[Group('fail')]
    public function test_it_can_replace_defined_uploaders()
    {
        $this->assertTrue(is_a($this->uploaderRepository->getUploadFor('image', 'withFiles'), SingleBase64Image::class, true));

        $this->uploaderRepository->addUploaderClasses([
            'image'    => SingleFile::class,
            'dropzone' => SingleFile::class,
        ], 'withFiles');

        $this->assertTrue($this->uploaderRepository->hasUploadFor('dropzone', 'withFiles'));
        $this->assertTrue(is_a($this->uploaderRepository->getUploadFor('dropzone', 'withFiles'), SingleFile::class, true));
        $this->assertTrue(is_a($this->uploaderRepository->getUploadFor('image', 'withFiles'), SingleFile::class, true));
    }

    #[Group('fail')]
    public function test_it_can_register_uploaders_in_a_new_group()
    {
        $this->assertFalse($this->uploaderRepository->hasUploadFor('image', 'newGroup'));

        $this->uploaderRepository->addUploaderClasses([
            'image' => SingleFile::class,
        ], 'newGroup');

        $this->assertTrue($this->uploaderRepository->hasUploadFor('image', 'newGroup'));
        $this->assertTrue(is_a($this->uploaderRepository->getUploadFor('image', 'newGroup'), SingleFile::class, true));
    }

    public function test_it_can_register_repeatable_uploaders()
    {
        CRUD::field('gallery')->subfields([
            [
                'name'      => 'image',
                'type'      => 'image',
                'withFiles' => true,
            ],
        ]);

        $this->assertTrue($this->uploaderRepository->hasRepeatableUploadersFor('gallery'));
    }

    #[Group('fail')]
    public function test_it_can_use_a_custom_uploader()
    {
        CRUD::field('upload')->type('upload')->withFiles(['uploader' => SingleBase64Image::class]);

        $this->assertTrue($this->uploaderRepository->hasUploadFor('upload', 'withFiles'));
        $this->assertTrue(is_a($this->uploaderRepository->getUploadFor('upload', 'withFiles'), SingleBase64Image::class, true));
    }
}
