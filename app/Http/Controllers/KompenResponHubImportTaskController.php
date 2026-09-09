<?php

namespace App\Http\Controllers;

use App\Http\Resources\KompenResponHubImportTaskResource;
use App\Models\KompenResponHubImportTask;
use Illuminate\Http\JsonResponse;

class KompenResponHubImportTaskController extends Controller
{
    public function show(KompenResponHubImportTask $importTask): JsonResponse
    {
        return (new KompenResponHubImportTaskResource($importTask))->response();
    }
}
