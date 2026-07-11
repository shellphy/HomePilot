<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    /**
     * 图片上传（进度照片、头像等；小程序 wx.uploadFile，字段名 image）。
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ], [
            'image.image' => '只能上传图片',
            'image.max' => '图片不能超过 10MB',
        ]);

        $path = $validated['image']->store('uploads', 'public');

        return response()->json([
            'url' => url(Storage::disk('public')->url($path)),
        ], 201);
    }
}
