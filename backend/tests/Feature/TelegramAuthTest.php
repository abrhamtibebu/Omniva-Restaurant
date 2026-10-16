<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TelegramAuthTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = '123456:ABC-DEF_test_token';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('telegram.bot_token', self::BOT_TOKEN);
        Config::set('telegram.auth_max_age_seconds', 86400);
    }

    public function test_auth_rejects_forged_init_data(): void
    {
        $this->postJson('/api/v1/telegram/auth', [
            'init_data' => 'user='.urlencode(json_encode(['id' => 99])).'&auth_date='.time().'&hash=deadbeef',
        ])->assertStatus(422)->assertJsonValidationErrors(['init_data']);
    }

    public function test_auth_rejects_unlinked_telegram_account(): void
    {
        $initData = $this->signedInitData(['id' => 555001, 'username' => 'newstaff']);

        $this->postJson('/api/v1/telegram/auth', [
            'init_data' => $initData,
        ])->assertStatus(422)->assertJsonValidationErrors(['init_data']);
    }

    public function test_link_then_auth_returns_sanctum_token(): void
    {
        $cashier = $this->actingAsRole(RoleSlug::Cashier);
        $initData = $this->signedInitData(['id' => 777001, 'username' => 'cashier_tg']);

        $this->postJson('/api/v1/telegram/link', [
            'init_data' => $initData,
        ])->assertOk()
            ->assertJsonPath('user.telegram_linked', true)
            ->assertJsonPath('user.telegram_username', 'cashier_tg');

        $this->assertSame(777001, (int) $cashier->fresh()->telegram_id);

        Sanctum::actingAs($cashier); // clear not needed — next call is public auth
        $this->app['auth']->forgetGuards();

        $response = $this->postJson('/api/v1/telegram/auth', [
            'init_data' => $initData,
            'device_name' => 'telegram-test',
        ])->assertOk();

        $response->assertJsonStructure(['token', 'user' => ['id', 'email', 'telegram_linked']]);
        $this->assertNotEmpty($response->json('token'));
        $this->assertTrue($response->json('user.telegram_linked'));
    }

    public function test_link_rejects_telegram_id_already_used(): void
    {
        $owner = $this->user(RoleSlug::Cashier, [
            'telegram_id' => 888001,
            'telegram_username' => 'taken',
        ]);
        $other = $this->actingAsRole(RoleSlug::Waiter);
        $initData = $this->signedInitData(['id' => 888001, 'username' => 'taken']);

        $this->postJson('/api/v1/telegram/link', [
            'init_data' => $initData,
        ])->assertStatus(422)->assertJsonValidationErrors(['init_data']);

        unset($owner, $other);
    }

    public function test_unlink_clears_telegram_fields(): void
    {
        $user = $this->actingAsRole(RoleSlug::Cashier);
        $user->update(['telegram_id' => 999001, 'telegram_username' => 'bye']);

        $this->deleteJson('/api/v1/telegram/link')
            ->assertOk()
            ->assertJsonPath('user.telegram_linked', false);

        $this->assertNull($user->fresh()->telegram_id);
    }

    /**
     * @param  array{id: int, username?: string, first_name?: string}  $user
     */
    private function signedInitData(array $user, ?int $authDate = null): string
    {
        $pairs = [
            'auth_date' => (string) ($authDate ?? time()),
            'user' => json_encode($user, JSON_UNESCAPED_UNICODE),
        ];

        ksort($pairs);
        $dataCheckString = collect($pairs)
            ->map(fn ($value, $key) => $key.'='.$value)
            ->implode("\n");

        $secretKey = hash_hmac('sha256', self::BOT_TOKEN, 'WebAppData', true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $pairs['hash'] = $hash;

        return http_build_query($pairs);
    }
}
