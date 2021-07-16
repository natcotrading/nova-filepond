<?php

namespace DigitalCreative\Filepond\Http\Controllers;

use App\Models\Image;
use DigitalCreative\Filepond\Filepond;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Nova\Contracts\RelatableField;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Laravel\Nova\Resource;

class FilepondController extends BaseController
{
    use ValidatesRequests;
    

    /**
     * Uploads the file to the temporary directory
     * and returns an encrypted path to the file
     *
     * @param NovaRequest $request
     *
     * @return Response
     */
    public function process(NovaRequest $request)
    {

        $attribute = $request->input('attribute');
        $prefixedAttribute = '__' . $attribute;
        $file = $request->file($prefixedAttribute);
        $resourceName = $request->input('resourceName');

        $request->offsetSet($attribute, $file);

        try {

            $resourceClass = Nova::resourceForKey($resourceName);

            /**
             * @var Resource $resource
             */
            $rules = $this->getCreationRules($resourceClass, $request);

            $this->validate($request, Arr::only($rules, $attribute));

        } catch (ValidationException $exception) {

            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], $exception->status);

        }

        $tempPath = '/tmp';
        $filePath = tempnam($tempPath, 'nova-filepond-');
        $filePath .= '.' . $file->guessClientExtension();
        $filePathParts = pathinfo($filePath);
        $finalPath = $file->move($filePathParts[ 'dirname' ], $filePathParts[ 'basename' ]);

        if (!$finalPath) {

            return response()->make('Could not save file', 500);

        }

        return response()->make(
            Filepond::getServerIdFromPath($finalPath->getRealPath())
        );

    }

    /**
     * Takes the given encrypted filepath and deletes
     * it if it hasn't been tampered with
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function revert(Request $request)
    {

        $filePath = Filepond::getPathFromServerId($request->getContent());

        if (unlink($filePath)) {

            return response()->make();

        }

        return response()->setStatusCode(500);

    }

    public function load(Request $request)
    {
        $image_id = Filepond::getPathFromServerId($request->input('serverId'));

        $image_model = Image::find($image_id);

        $disk = 'local';
        if($image_model->cloud) {
            $disk = 'cloud';
        }

        $response = response(Storage::disk($disk)->get($image_model->getPath()))
            ->header('Content-Disposition', "inline; name=\"$image_model->name\"; filename=\"$image_model->name\"")
            ->header('Content-Length', Storage::disk($disk)->size($image_model->getPath()));

        if ($mimeType = Filepond::guessMimeType($image_model->inferExtension())) {

            $response->header('Content-Type', $mimeType);

        }

        return $response;

    }

    private function getCreationRules(string $resource, NovaRequest $request): array
    {
        return (new $resource($resource::newModel()))
            ->creationFields($request)
            ->reject(function ($field) use ($request) {
                return $field->isReadonly($request) || $field instanceof RelatableField;
            })
            ->mapWithKeys(function ($field) use ($request) {
                return $field->getCreationRules($request);
            })->all();
    }

}
