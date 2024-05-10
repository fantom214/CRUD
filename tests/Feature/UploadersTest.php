<?php

namespace Tests\Feature;

use Backpack\CRUD\Tests\Config\CrudPanel\BaseDBCrudPanel;
use Backpack\CRUD\Tests\config\Http\Controllers\UploaderCrudController;
use Backpack\CRUD\Tests\config\Models\Uploader;
use Backpack\CRUD\Tests\config\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadersTest extends BaseDBCrudPanel
{
    protected function defineRoutes($router)
    {
        $router->crud(config('backpack.base.route_prefix').'/uploader', UploaderCrudController::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('uploaders');
        $this->actingAs(User::find(1));
    }

    public function test_it_can_access_the_uploaders_create_page()
    {
        $response = $this->get(config('backpack.base.route_prefix').'/uploader/create');
        $response->assertStatus(200);
    }

    public function test_it_can_store_uploaded_files()
    {
        $response = $this->post(config('backpack.base.route_prefix').'/uploader', [
            'upload' => UploadedFile::fake()->image('avatar.jpg'),
            'upload_multiple' => [UploadedFile::fake()->image('avatar1.jpg'), UploadedFile::fake()->image('avatar2.jpg')],
        ]);

        $response->assertStatus(302);

        $response->assertRedirect(config('backpack.base.route_prefix').'/uploader');

        $this->assertDatabaseCount('uploaders', 1);

        $files = Storage::disk('uploaders')->allFiles();

        $this->assertEquals(3, count($files));

        $this->assertDatabaseHas('uploaders', [
            'upload' => 'avatar.jpg',
            'upload_multiple' => json_encode(['avatar1.jpg',  'avatar2.jpg']),
        ]);

        $this->assertTrue(Storage::disk('uploaders')->exists('avatar.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar1.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar2.jpg'));
    }

    public function test_it_display_the_edit_page_without_files()
    {
        self::initUploader();

        $response = $this->get(config('backpack.base.route_prefix').'/uploader/1/edit');
        $response->assertStatus(200);
    }

    public function test_it_display_the_upload_page_with_files()
    {
        self::initUploaderWithFiles();
        $response = $this->get(config('backpack.base.route_prefix').'/uploader/1/edit');

        $response->assertStatus(200);

        $response->assertSee('avatar.jpg');
        $response->assertSee('avatar1.jpg');
        $response->assertSee('avatar2.jpg');
    }

    public function test_it_can_update_uploaded_files()
    {
        self::initUploaderWithFiles();

        $response = $this->put(config('backpack.base.route_prefix').'/uploader/1', [
            'upload' => UploadedFile::fake()->image('avatar4.jpg'),
            'upload_multiple' => [UploadedFile::fake()->image('avatar5.jpg'), UploadedFile::fake()->image('avatar6.jpg')],
            'clear_upload_multiple' => ['avatar2.jpg',  'avatar3.jpg'],
            'id' => 1,
        ]);

        $response->assertStatus(302);

        $response->assertRedirect(config('backpack.base.route_prefix').'/uploader');

        $this->assertDatabaseCount('uploaders', 1);

        $this->assertDatabaseHas('uploaders', [
            'upload' => 'avatar4.jpg',
            'upload_multiple' => json_encode(['avatar5.jpg',  'avatar6.jpg']),
        ]);

        $files = Storage::disk('uploaders')->allFiles();

        $this->assertEquals(3, count($files));

        $this->assertTrue(Storage::disk('uploaders')->exists('avatar4.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar5.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar6.jpg'));
    }

    /**
     * @group fail
     */
    public function test_it_can_delete_uploaded_files()
    {
        self::initUploaderWithFiles();

        $response = $this->delete(config('backpack.base.route_prefix').'/uploader/1');

        $response->assertStatus(200);

        $this->assertDatabaseCount('uploaders', 0);

        $files = Storage::disk('uploaders')->allFiles();

        $this->assertEquals(0, count($files));
    }

    /**
     * @group fail
     */
    public function test_it_keeps_previous_values_unchaged_when_not_deleted()
    {
        self::initUploaderWithFiles();

        $response = $this->put(config('backpack.base.route_prefix').'/uploader/1', [
            'upload_multiple' => ['avatar2.jpg',  'avatar3.jpg'],
            'id' => 1,
        ]);

        $response->assertStatus(302);

        $response->assertRedirect(config('backpack.base.route_prefix').'/uploader');

        $this->assertDatabaseCount('uploaders', 1);

        $this->assertDatabaseHas('uploaders', [
            'upload' => 'avatar1.jpg',
            'upload_multiple' => json_encode(['avatar2.jpg',  'avatar3.jpg']),
        ]);

        $files = Storage::disk('uploaders')->allFiles();

        $this->assertEquals(3, count($files));

        $this->assertTrue(Storage::disk('uploaders')->exists('avatar1.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar2.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar3.jpg'));
    }

    /**
     * @group fail
     */
    public function test_upload_multiple_can_delete_uploaded_files_and_add_at_the_same_time()
    {
        self::initUploaderWithFiles();

        $response = $this->put(config('backpack.base.route_prefix').'/uploader/1', [
            'upload_multiple' => [UploadedFile::fake()->image('avatar4.jpg'), UploadedFile::fake()->image('avatar5.jpg')],
            'clear_upload_multiple' => ['avatar2.jpg'],
            'id' => 1,
        ]);

        $response->assertStatus(302);

        $response->assertRedirect(config('backpack.base.route_prefix').'/uploader');

        $this->assertDatabaseCount('uploaders', 1);

        $this->assertDatabaseHas('uploaders', [
            'upload' => 'avatar1.jpg',
            'upload_multiple' => json_encode(['avatar3.jpg', 'avatar4.jpg',  'avatar5.jpg']),
        ]);

        $files = Storage::disk('uploaders')->allFiles();

        $this->assertEquals(4, count($files));

        $this->assertTrue(Storage::disk('uploaders')->exists('avatar1.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar3.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar4.jpg'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar5.jpg'));
    }

    protected static function initUploaderWithFiles()
    {
        UploadedFile::fake()->image('avatar1.jpg')->storeAs('', 'avatar1.jpg', ['disk' => 'uploaders']);
        UploadedFile::fake()->image('avatar2.jpg')->storeAs('', 'avatar2.jpg', ['disk' => 'uploaders']);
        UploadedFile::fake()->image('avatar3.jpg')->storeAs('', 'avatar3.jpg', ['disk' => 'uploaders']);

        Uploader::create([
            'upload' => 'avatar1.jpg',
            'upload_multiple' => json_encode(['avatar2.jpg',  'avatar3.jpg']),
        ]);
    }

    protected static function initUploader()
    {
        Uploader::create([
            'upload' => null,
            'upload_multiple' => null,
        ]);
    }
}
