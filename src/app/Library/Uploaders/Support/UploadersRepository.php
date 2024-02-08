<?php

namespace Backpack\CRUD\app\Library\Uploaders\Support;

use Backpack\CRUD\app\Library\Uploaders\Support\Interfaces\UploaderInterface;
use Illuminate\Support\Str;

final class UploadersRepository
{
    /**
     * The array of uploaders classes for field types.
     */
    private array $uploaderClasses;

    /**
     * Uploaders registered in a repeatable group.
     */
    private array $repeatableUploaders = [];

    /**
     * Uploaders that have already been handled (events registered) for each field/column instance.
     */
    private array $handledUploaders = [];

    public function __construct()
    {
        $this->uploaderClasses = config('backpack.crud.uploaders');
    }

    /**
     * Mark the given uploader as handled.
     */
    public function markAsHandled(string $objectName): void
    {
        if (! in_array($objectName, $this->handledUploaders)) {
            $this->handledUploaders[] = $objectName;
        }
    }

    /**
     * Check if the given uploader for field/column have been handled.
     */
    public function isUploadHandled(string $objectName): bool
    {
        return in_array($objectName, $this->handledUploaders);
    }

    /**
     * Check if there are uploads for the give object(field/column) type.
     */
    public function hasUploadFor(string $objectType, string $group): bool
    {
        return array_key_exists($objectType, $this->uploaderClasses[$group]);
    }

    /**
     * Return the uploader for the given object type.
     */
    public function getUploadFor(string $objectType, string $group): string
    {
        return $this->uploaderClasses[$group][$objectType];
    }

    /**
     * Register new uploaders or override existing ones.
     */
    public function addUploaderClasses(array $uploaders, string $group): void
    {
        $this->uploaderClasses[$group] = array_merge($this->getGroupUploadersClasses($group), $uploaders);
    }

    /**
     * Return the uploaders classes for the given group.
     */
    private function getGroupUploadersClasses(string $group): array
    {
        return $this->uploaderClasses[$group] ?? [];
    }

    /**
     * Register the specified uploader for the given upload name.
     */
    public function registerRepeatableUploader(string $uploadName, UploaderInterface $uploader): void
    {
        if (! array_key_exists($uploadName, $this->repeatableUploaders) || ! in_array($uploader, $this->repeatableUploaders[$uploadName])) {
            $this->repeatableUploaders[$uploadName][] = $uploader;
        }
    }

    /**
     * Check if there are uploaders registered for the given upload name.
     */
    public function hasRepeatableUploadersFor(string $uploadName): bool
    {
        return array_key_exists($uploadName, $this->repeatableUploaders);
    }

    /**
     * Get the repeatable uploaders for the given upload name.
     */
    public function getRepeatableUploadersFor(string $uploadName): array
    {
        return $this->repeatableUploaders[$uploadName] ?? [];
    }

    /**
     * Check if the specified upload is registered for the given repeatable uploads.
     */
    public function isUploadRegistered(string $uploadName, UploaderInterface $upload): bool
    {
        return $this->hasRepeatableUploadersFor($uploadName) && in_array($upload->getName(), $this->getRegisteredUploadNames($uploadName));
    }

    /**
     * Return the registered uploaders names for the given repeatable upload name.
     */
    public function getRegisteredUploadNames(string $uploadName): array
    {
        return array_map(function ($uploader) {
            return $uploader->getName();
        }, $this->getRepeatableUploadersFor($uploadName));
    }

    /**
     * Get the uploaders classes for the given group of uploaders.
     */
    public function getAjaxUploadTypes(string $group = 'withFiles'): array
    {
        $ajaxFieldTypes = [];
        foreach ($this->uploaderClasses[$group] as $fieldType => $uploader) {
            if (is_a($uploader, 'Backpack\Pro\Uploads\BackpackAjaxUploader', true)) {
                $ajaxFieldTypes[] = $fieldType;
            }
        }

        return $ajaxFieldTypes;
    }

    /**
     * Get an uploader instance for a given crud object.
     */
    public function getUploaderInstance(string $requestInputName, array $crudObject): UploaderInterface
    {
        if (! $this->isValidUploadField($requestInputName)) {
            abort(500, 'Invalid field for upload.');
        }

        if (strpos($requestInputName, '#') !== false) {
            $repeatableContainerName = Str::before($requestInputName, '#');
            $requestInputName = Str::after($requestInputName, '#');
            $uploaders = $this->getRepeatableUploadersFor($repeatableContainerName);
            //TODO: Implement the logic for repeatable uploaders
            dd('here');
        }

        if (! $uploadType = $this->getUploadCrudObjectMacroType($crudObject)) {
            abort(500, 'There is no uploader defined for the given field type.');
        }

        $uploaderConfiguration = $crudObject[$uploadType] ?? [];
        $uploaderConfiguration = ! is_array($uploaderConfiguration) ? [] : $uploaderConfiguration;
        $uploaderClass = $this->getUploadFor($crudObject['type'], $uploadType);

        return new $uploaderClass(['name' => $requestInputName], $uploaderConfiguration);
    }

    /**
     * Get the upload field macro type for the given object.
     */
    private function getUploadCrudObjectMacroType(array $crudObject): string
    {
        return isset($crudObject['withFiles']) ? 'withFiles' : ($crudObject['withMedia'] ? 'withMedia' : null);
    }

    private function isValidUploadField($fieldName)
    {
        if (Str::contains($fieldName, '#')) {
            $container = Str::before($fieldName, '#');
            $fieldName = Str::after($fieldName, '#');
            $field = array_filter(CRUD::fields()[$container]['subfields'] ?? [], function ($item) use ($fieldName) {
                return $item['name'] === $fieldName && in_array($item['type'], $this->getAjaxFieldUploadTypes($fieldName));
            });

            return ! empty($field);
        }

        return isset(CRUD::fields()[$fieldName]) && in_array(CRUD::fields()[$fieldName]['type'], $this->getAjaxFieldUploadTypes($fieldName));
    }
}
