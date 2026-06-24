<?php

namespace App\Support;

class UserFacingMail
{
    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @param  TResult|null  $fallbackResult
     * @return array{ok: bool, result: TResult|null}
     */
    public function attempt(callable $callback, mixed $fallbackResult = null): array
    {
        try {
            return [
                'ok' => true,
                'result' => $callback(),
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'ok' => false,
                'result' => $fallbackResult,
            ];
        }
    }
}
