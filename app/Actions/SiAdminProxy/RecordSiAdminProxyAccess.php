<?php

namespace App\Actions\SiAdminProxy;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecordSiAdminProxyAccess
{
    /**
     * @param  array{email: string, nonce: string, role: string, timestamp: int, user_id: string}  $identity
     */
    public function record(Request $request, array $identity): void
    {
        try {
            DB::connection(config('kompen-respon-hub.database_connection'))
                ->table('sikompen_proxy_access_logs')
                ->insert([
                    'si_admin_user_id' => $identity['user_id'],
                    'email' => $identity['email'],
                    'role' => $identity['role'],
                    'nonce_hash' => hash('sha256', $identity['nonce']),
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                    'accessed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (UniqueConstraintViolationException) {
            abort(403);
        }
    }
}
