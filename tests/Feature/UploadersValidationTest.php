<?php

namespace Tests\Feature;

use Backpack\CRUD\Tests\config\CrudPanel\BaseDBCrudPanel;
use Backpack\CRUD\Tests\config\Http\Controllers\UploaderValidationCrudController;
use Backpack\CRUD\Tests\config\Models\Uploader;
use Backpack\CRUD\Tests\config\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;

class UploadersValidationTest extends BaseDBCrudPanel
{
    private string $testBaseUrl;

    protected function defineRoutes($router)
    {
        $router->crud(config('backpack.base.route_prefix').'/uploader-validation', UploaderValidationCrudController::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->testBaseUrl = config('backpack.base.route_prefix').'/uploader-validation';
        Storage::fake('uploaders');
        $this->actingAs(User::find(1));
    }
    
    
    public function test_it_can_access_the_uploaders_create_page()
    {
        $response = $this->get($this->testBaseUrl.'/create');
        $response->assertStatus(200);
    }

    public function test_it_can_store_uploaded_files()
    {
        $response = $this->post($this->testBaseUrl, [
            'upload'          => UploadedFile::fake()->create('avatar.pdf'),
            'upload_multiple' => [UploadedFile::fake()->create('avatar1.pdf'), UploadedFile::fake()->create('avatar2.pdf')],
        ]);

        $response->assertStatus(302);

        $response->assertRedirect($this->testBaseUrl);

        $this->assertDatabaseCount('uploaders', 1);

        $files = Storage::disk('uploaders')->allFiles();

        $this->assertEquals(3, count($files));

        $this->assertDatabaseHas('uploaders', [
            'upload'          => 'avatar.pdf',
            'upload_multiple' => json_encode(['avatar1.pdf',  'avatar2.pdf']),
        ]);

        $this->assertTrue(Storage::disk('uploaders')->exists('avatar.pdf'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar1.pdf'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar2.pdf'));
    }

    public function test_it_display_the_edit_page_without_files()
    {
        self::initUploader();

        $response = $this->get($this->testBaseUrl.'/1/edit');
        $response->assertStatus(200);
    }

    public function test_it_display_the_upload_page_with_files()
    {
        self::initUploaderWithImages();

        $response = $this->get($this->testBaseUrl.'/1/edit');

        $response->assertStatus(200);

        $response->assertSee('avatar1.jpg');
        $response->assertSee('avatar2.jpg');
        $response->assertSee('avatar3.jpg');
    }

    public function test_it_can_update_uploaded_files()
    {
        self::initUploaderWithFiles();

        $response = $this->put($this->testBaseUrl.'/1', [
            'upload'                => UploadedFile::fake()->create('avatar4.pdf'),
            'upload_multiple'       => [UploadedFile::fake()->create('avatar5.pdf'), UploadedFile::fake()->create('avatar6.pdf')],
            'clear_upload_multiple' => ['avatar2.pdf',  'avatar3.pdf'],
            'id'                    => 1,
        ]);

        $response->assertStatus(302);

        $response->assertRedirect($this->testBaseUrl);

        $this->assertDatabaseCount('uploaders', 1);

        $this->assertDatabaseHas('uploaders', [
            'upload'          => 'avatar4.pdf',
            'upload_multiple' => json_encode(['avatar5.pdf',  'avatar6.pdf']),
        ]);

        $files = Storage::disk('uploaders')->allFiles();

        $this->assertEquals(3, count($files));

        $this->assertTrue(Storage::disk('uploaders')->exists('avatar4.pdf'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar5.pdf'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar6.pdf'));
    }
    
    public function test_it_can_delete_uploaded_files()
    {
        self::initUploaderWithImages();

        $response = $this->delete($this->testBaseUrl.'/1');

        $response->assertStatus(200);

        $this->assertDatabaseCount('uploaders', 0);

        $files = Storage::disk('uploaders')->allFiles();

        $this->assertEquals(0, count($files));
    }

    public function test_it_keeps_previous_values_unchaged_when_not_deleted()
    {
        self::initUploaderWithFiles();

        $response = $this->put($this->testBaseUrl.'/1', [
            'upload_multiple' => ['avatar2.pdf',  'avatar3.pdf'],
            'id'              => 1,
        ]);

        $response->assertStatus(302);

        $response->assertRedirect($this->testBaseUrl);

        $this->assertDatabaseCount('uploaders', 1);

        $this->assertDatabaseHas('uploaders', [
            'upload'          => 'avatar1.pdf',
            'upload_multiple' => json_encode(['avatar2.pdf',  'avatar3.pdf']),
        ]);

        $files = Storage::disk('uploaders')->allFiles();

        $this->assertEquals(3, count($files));

        $this->assertTrue(Storage::disk('uploaders')->exists('avatar1.pdf'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar2.pdf'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar3.pdf'));
    }

    public function test_upload_multiple_can_delete_uploaded_files_and_add_at_the_same_time()
    {
        self::initUploaderWithFiles();

        $response = $this->put($this->testBaseUrl.'/1', [
            'upload_multiple'       => [UploadedFile::fake()->create('avatar4.pdf'), UploadedFile::fake()->create('avatar5.pdf')],
            'clear_upload_multiple' => ['avatar2.pdf'],
            'id'                    => 1,
        ]);

        $response->assertStatus(302);

        $response->assertRedirect($this->testBaseUrl);

        $this->assertDatabaseCount('uploaders', 1);

        $this->assertDatabaseHas('uploaders', [
            'upload'          => 'avatar1.pdf',
            'upload_multiple' => json_encode(['avatar3.pdf', 'avatar4.pdf',  'avatar5.pdf']),
        ]);

        $files = Storage::disk('uploaders')->allFiles();

        $this->assertEquals(4, count($files));

        $this->assertTrue(Storage::disk('uploaders')->exists('avatar1.pdf'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar3.pdf'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar4.pdf'));
        $this->assertTrue(Storage::disk('uploaders')->exists('avatar5.pdf'));
    }

    public function test_it_validates_files_on_a_single_upload()
    {
        $response = $this->post($this->testBaseUrl, [
            'upload'          => 'not-a-file',
            'upload_multiple' => [UploadedFile::fake()->create('avatar1.pdf'), UploadedFile::fake()->create('avatar2.pdf')],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('upload');

        $this->assertDatabaseCount('uploaders', 0);
    }

    public function test_it_validates_files_on_multiple_uploader()
    {
        $response = $this->post($this->testBaseUrl, [
            'upload'          => UploadedFile::fake()->create('avatar.pdf'),
            'upload_multiple' => [UploadedFile::fake()->create('avatar1.pdf'), 'not-a-file'],
        ]);
        
        $response->assertStatus(302);
        $response->assertSessionHasErrors('upload_multiple');

        $this->assertDatabaseCount('uploaders', 0);
    }
   
    public function test_it_validates_mime_types_on_single_and_multi_uploads()
    {
        $response = $this->post($this->testBaseUrl, [
            'upload'          => UploadedFile::fake()->create('avatar2.jpg'),
            'upload_multiple' => [UploadedFile::fake()->create('avatar1.jpg'), UploadedFile::fake()->create('avatar3.jpg')],
        ]);
        
        $response->assertStatus(302);

        $response->assertSessionHasErrors('upload_multiple');
        $response->assertSessionHasErrors('upload');

        // assert the error content
        $this->assertEquals('The upload multiple field must be a file of type: pdf.', session('errors')->get('upload_multiple')[0]);
        $this->assertEquals('The upload field must be a file of type: pdf.', session('errors')->get('upload')[0]);

        $this->assertDatabaseCount('uploaders', 0);
    }

    public function test_it_validates_file_size_on_single_and_multi_uploads()
    {
        $response = $this->post($this->testBaseUrl, [
            'upload'          => UploadedFile::fake()->create('avatar.pdf', 2000),
            'upload_multiple' => [UploadedFile::fake()->create('avatar1.pdf', 2000), UploadedFile::fake()->create('avatar2.pdf', 2000)],
        ]);

        $response->assertStatus(302);

        $response->assertSessionHasErrors('upload_multiple');
        $response->assertSessionHasErrors('upload');

        // assert the error content
        $this->assertEquals('The upload multiple field must not be greater than 1024 kilobytes.', session('errors')->get('upload_multiple')[0]);
        $this->assertEquals('The upload field must not be greater than 1024 kilobytes.', session('errors')->get('upload')[0]);

        $this->assertDatabaseCount('uploaders', 0);
    }
    
    public function test_it_validates_min_files_on_multi_uploads()
    {
        $response = $this->post($this->testBaseUrl, [
            'upload'          => UploadedFile::fake()->create('avatar.pdf'),
            'upload_multiple' => [UploadedFile::fake()->create('avatar1.pdf')],
        ]);

        $response->assertStatus(302);

        $response->assertSessionHasErrors('upload_multiple');

        // assert the error content
        $this->assertEquals('The upload multiple field must have at least 2 items.', session('errors')->get('upload_multiple')[0]);

        $this->assertDatabaseCount('uploaders', 0);
    }

    public function test_it_validates_required_files_on_single_and_multi_uploads()
    {
        $response = $this->post($this->testBaseUrl, [
            'upload'          => null,
            'upload_multiple' => null,
        ]);

        $response->assertStatus(302);

        $response->assertSessionHasErrors('upload');
        $response->assertSessionHasErrors('upload_multiple');

        // assert the error content
        $this->assertEquals('The upload field is required.', session('errors')->get('upload')[0]);
        $this->assertEquals('The upload multiple field is required.', session('errors')->get('upload_multiple')[0]);

        $this->assertDatabaseCount('uploaders', 0);
    }

    
    public function test_it_validates_required_when_not_present_in_request()
    {
        $response = $this->post($this->testBaseUrl, []);

        $response->assertStatus(302);

        $response->assertSessionHasErrors('upload');
        $response->assertSessionHasErrors('upload_multiple');

        $this->assertEquals('The upload field is required.', session('errors')->get('upload')[0]);
        $this->assertEquals('The upload multiple field is required.', session('errors')->get('upload_multiple')[0]);

        $this->assertDatabaseCount('uploaders', 0);
    }
    #[Group('fail')]
    public function test_it_validates_required_files_on_single_and_multi_uploads_when_updating()
    {
        self::initUploader();

        $response = $this->put($this->testBaseUrl.'/1', [
            'upload'          => null,
            'upload_multiple' => null,
            'id'              => 1,
        ]);

        $response->assertStatus(302);

        $response->assertSessionHasErrors('upload');
        $response->assertSessionHasErrors('upload_multiple');

        // assert the error content
        $this->assertEquals('The upload field is required.', session('errors')->get('upload')[0]);
        $this->assertEquals('The upload multiple field is required.', session('errors')->get('upload_multiple')[0]);

        $this->assertDatabaseCount('uploaders', 1);
    }
    
    public function test_it_validates_required_files_on_single_and_multi_uploads_when_updating_with_files()
    {
        self::initUploaderWithFiles();

        $response = $this->put($this->testBaseUrl.'/1', [
            'upload'          => null,
            'upload_multiple' => null,
            'clear_upload_multiple' => ['avatar2.pdf',  'avatar3.pdf'],
            'id'              => 1,
        ]);

        $response->assertStatus(302);

        $response->assertSessionHasErrors('upload');
        $response->assertSessionHasErrors('upload_multiple');

        // assert the error content
        $this->assertEquals('The upload field is required.', session('errors')->get('upload')[0]);
        $this->assertEquals('The upload multiple field is required.', session('errors')->get('upload_multiple')[0]);

        $this->assertDatabaseCount('uploaders', 1);
    }
    
    #[Group('fail')]
    public function test_it_validates_min_files_on_multi_uploads_when_updating()
    {
        self::initUploaderWithFiles();

        $response = $this->put($this->testBaseUrl.'/1', [
            'upload_multiple' => [UploadedFile::fake()->create('avatar1.pdf')],
            'clear_upload_multiple' => ['avatar2.pdf',  'avatar3.pdf'],
            'id'              => 1,
        ]);

        $response->assertStatus(302);

        $response->assertSessionHasErrors('upload_multiple');

        // assert the error content
        $this->assertEquals('The upload multiple field must have at least 2 items.', session('errors')->get('upload_multiple')[0]);

        $this->assertDatabaseCount('uploaders', 1);
    }

    protected static function initUploaderWithImages()
    {
        UploadedFile::fake()->image('avatar1.jpg')->storeAs('', 'avatar1.jpg', ['disk' => 'uploaders']);
        UploadedFile::fake()->image('avatar2.jpg')->storeAs('', 'avatar2.jpg', ['disk' => 'uploaders']);
        UploadedFile::fake()->image('avatar3.jpg')->storeAs('', 'avatar3.jpg', ['disk' => 'uploaders']);

        Uploader::create([
            'upload'          => 'avatar1.jpg',
            'upload_multiple' => json_encode(['avatar2.jpg',  'avatar3.jpg']),
        ]);
    }

    protected static function initUploaderWithFiles()
    {
        UploadedFile::fake()->create('avatar1.pdf')->storeAs('', 'avatar1.pdf', ['disk' => 'uploaders']);
        UploadedFile::fake()->create('avatar2.pdf')->storeAs('', 'avatar2.pdf', ['disk' => 'uploaders']);
        UploadedFile::fake()->create('avatar3.pdf')->storeAs('', 'avatar3.pdf', ['disk' => 'uploaders']);

        Uploader::create([
            'upload'          => 'avatar1.pdf',
            'upload_multiple' => json_encode(['avatar2.pdf',  'avatar3.pdf']),
        ]);
    }

    protected static function initUploader()
    {
        Uploader::create([
            'upload'          => null,
            'upload_multiple' => null,
        ]);
    }
}
