<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Services\ResidentTodoList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TodoController extends Controller
{
    use ResolvesResident;

    public function __construct(private ResidentTodoList $todos) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->todos->for($this->resident($request)),
        ]);
    }
}
