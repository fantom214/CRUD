<?php

namespace Tests\Feature;

use Backpack\CRUD\Tests\Config\CrudPanel\BaseDBCrudPanel;
use Backpack\CRUD\Tests\config\Http\Controllers\UploaderCrudController;
use Backpack\CRUD\Tests\config\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadersTest extends BaseDBCrudPanel
{
    protected function defineRoutes($router)
    {
        $router->crud(config('backpack.base.route_prefix').'/uploader', UploaderCrudController::class);
    }

    /**
     * @group fail
     */
    public function test_it_can_access_the_uploaders_create_page()
    {
        $this->actingAs(User::find(1));
        $response = $this->get(config('backpack.base.route_prefix').'/uploader/create');
        $response->assertStatus(200);
    }

    /**
     * @group fail
     */
    public function test_it_can_store_uploaded_files()
    {
        Storage::fake('uploaders');

        $this->actingAs(User::find(1));
        $response = $this->post(config('backpack.base.route_prefix').'/uploader', [
            'upload'          => UploadedFile::fake()->image('avatar.jpg'),

        ]);
        //dd($response);
        $response->assertStatus(302);

        $response->assertRedirect(config('backpack.base.route_prefix').'/uploader');

        $this->assertDatabaseCount('uploaders', 1);

        $files = Storage::disk('uploaders')->allFiles();

        $this->assertEquals(1, count($files));
    }
}
