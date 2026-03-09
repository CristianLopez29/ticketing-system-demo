<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function download(Request $request): StreamedResponse
    {
        $file = $request->query('file');
        $name = is_string($file) ? basename($file) : null;
        if ($name === null) {
            abort(400);
        }

        $disk = Storage::disk('reports');
        if (!$disk->exists($name)) {
            abort(404);
        }

        return $disk->download($name);
    }
}

