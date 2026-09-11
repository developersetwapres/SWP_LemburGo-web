<?php

namespace App\Traits;

trait JsonResponseTrait
{
    public static function success($message, $data = null, $status = 200)
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
            'status_code' => $status,
        ], $status);
    }

    public static function error($message, $errors = null, $status = 500)
    {
        return response()->json([
            'message' => $message,
            'errors' => $errors,
            'status_code' => $status,
        ], $status);
    }
}
