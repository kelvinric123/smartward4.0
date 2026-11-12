<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\EkadBedMapping;
use App\Models\EkadConfiguration;
use App\Models\Hospital;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Screen Status on /ekad reads each screen's battery, signal, online state
 * and paint jobs from SEEKINK and matches them to the bed mappings by MAC.
 */
class EkadScreenStatusTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'http://seekink.test/prod-api';

    private User $user;
    private EkadConfiguration $config;
    /** What the fake SEEKINK answers /device/label/list with the token "token" */
    private array $labelListAnswer;
    private array $taskQueries = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-13 12:45:00');

        $this->labelListAnswer = ['code' => 200, 'msg' => 'Operation Successful！', 'total' => 2, 'rows' => [
            $this->screen('D43D3971732A', 'D611', power: 52, signal: -62, receiveTime: '2026-10-11 06:26:20'),
            $this->screen('D43D39716B4C', 'D514', power: 0, signal: -88, receiveTime: '2026-10-13 12:30:00'),
        ]];

        // Never reach a real SEEKINK from a test
        Http::preventStrayRequests();
        Http::fake(function (HttpRequest $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $token = $request->header('Authorization')[0] ?? '';

            if (str_ends_with($request->url(), '/api/v1/user/login')) {
                return Http::response(['code' => 200, 'msg' => 'Operation Successful！', 'data' => 'fresh-token']);
            }
            if (str_contains($request->url(), '/device/label/list')) {
                return $token === 'Bearer stale-token'
                    ? Http::response(['code' => 401, 'msg' => 'Authentication failed'])
                    : Http::response($this->labelListAnswer);
            }
            if (str_contains($request->url(), '/device/task/list')) {
                $this->taskQueries[] = $query;
                $rows = [
                    $this->task('D43D3971732A', status: 2, code: 110, createTime: '2026-10-13 12:40:00'),
                    $this->task('D43D3971732A', status: 1, code: 113, createTime: '2026-10-13 09:00:00'),
                    $this->task('D43D39716B4C', status: 1, code: 113, createTime: '2026-10-13 08:00:00'),
                ];
                if (isset($query['labelMac'])) {
                    $rows = array_values(array_filter($rows, fn ($row) => $row['labelMac'] === $query['labelMac']));
                }

                return Http::response(['code' => 200, 'msg' => 'Operation Successful！', 'total' => count($rows), 'rows' => $rows]);
            }

            return null;
        });

        $this->user = User::factory()->create();
        $this->config = EkadConfiguration::create([
            'base_url' => self::BASE,
            'username' => 'admin',
            'password' => 'secret',
            'template_id' => '2000477842757914624',
            'bearer_token' => 'token',
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $type = WardType::create(['code' => 'MED', 'name' => 'Medical', 'is_active' => true]);
        $ward = Ward::create([
            'hospital_id' => Hospital::create(['name' => 'Test Hospital'])->id,
            'ward_code' => 'W6', 'ward_name' => 'Ward 6', 'ward_type_id' => $type->id, 'is_active' => true,
        ]);
        foreach (['D611' => 'd4:3d:39:71:73:2a', 'D612' => 'D43D39000001'] as $bedNumber => $mac) {
            $bed = Bed::create([
                'ward_id' => $ward->id, 'bed_number' => $bedNumber, 'bed_id' => $bedNumber,
                'bed_display_name' => $bedNumber, 'status' => 'available', 'is_active' => true,
            ]);
            EkadBedMapping::create(['bed_id' => $bed->id, 'mac_address' => $mac, 'is_active' => true]);
        }
    }

    private function screen(string $mac, string $nickname, int $power, int $signal, string $receiveTime): array
    {
        return [
            'labelMac' => $mac, 'nickname' => $nickname, 'screenSize' => '11.6', 'resolution' => '960*640', 'color' => 'BWR',
            'labelVersion' => 'H1_V2.2.10', 'registerStatus' => 1, 'onlineStatus' => 1, 'power' => $power,
            'bluetoothSignal' => $signal, 'receiveTime' => $receiveTime, 'secretKey' => 'never-shown',
        ];
    }

    private function task(string $mac, int $status, int $code, string $createTime): array
    {
        return [
            'labelMac' => $mac, 'taskid' => md5($mac . $createTime), 'status' => $status, 'type' => 0,
            'errorcode' => $code, 'createTime' => $createTime, 'receiveTime' => $createTime,
        ];
    }

    public function test_each_mapped_screen_gets_its_seekink_state_and_last_paint(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('ekad.screen-status'))->assertOk();

        $response->assertJsonPath('success', true);
        $d611 = collect($response->json('screens'))->firstWhere('bed', 'D611');
        $this->assertSame('D43D3971732A', $d611['mac'], 'a mapping typed with colons still matches');
        $this->assertTrue($d611['found']);
        $this->assertSame('D611', $d611['nickname']);
        $this->assertTrue($d611['online']);
        $this->assertSame(52, $d611['battery']);
        $this->assertFalse($d611['low_battery']);
        $this->assertSame('good', $d611['signal_level']);
        $this->assertSame('2026-10-11 06:26:20', $d611['last_report']);
        $this->assertSame('2 days ago', $d611['last_report_ago']);
        // SEEKINK lists the newest job first
        $this->assertTrue($d611['last_paint']['failed']);
        $this->assertSame('Failed', $d611['last_paint']['status_label']);
        $this->assertSame('Bluetooth connection timed out', $d611['last_paint']['code_label']);
        $this->assertStringNotContainsString('never-shown', $response->getContent());
    }

    public function test_it_counts_problems_and_lists_screens_on_either_side_only(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('ekad.screen-status'))->assertOk();

        $response->assertJsonPath('summary', [
            'mapped' => 2, 'online' => 1, 'offline' => 0, 'low_battery' => 0, 'paint_failed' => 1,
            'missing' => 1, 'unmapped' => 1, 'seekink_screens' => 2,
        ]);
        $missing = collect($response->json('screens'))->firstWhere('bed', 'D612');
        $this->assertFalse($missing['found']);
        $this->assertNull($missing['online']);

        $unmapped = $response->json('unmapped');
        $this->assertCount(1, $unmapped);
        $this->assertSame('D514', $unmapped[0]['nickname']);
        $this->assertSame(0, $unmapped[0]['battery']);
        $this->assertTrue($unmapped[0]['low_battery']);
        $this->assertSame('weak', $unmapped[0]['signal_level']);
        $this->assertSame('Painted', $unmapped[0]['last_paint']['status_label']);
    }

    public function test_an_expired_token_logs_in_again_once(): void
    {
        $this->config->update(['bearer_token' => 'stale-token']);

        $this->actingAs($this->user)->getJson(route('ekad.screen-status'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.seekink_screens', 2);

        $this->assertSame('fresh-token', $this->config->fresh()->bearer_token);
        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/api/v1/user/login')
            && $request['username'] === 'admin');
    }

    public function test_a_seekink_error_is_shown_not_thrown(): void
    {
        $this->labelListAnswer = ['code' => 500, 'msg' => 'Database is down'];

        $this->actingAs($this->user)->getJson(route('ekad.screen-status'))
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'SEEKINK answered /device/label/list with Database is down');
    }

    public function test_history_reads_one_screens_paint_jobs(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('ekad.screen-status.paints', 'd4:3d:39:71:73:2a'))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('D43D3971732A', end($this->taskQueries)['labelMac']);
        $this->assertSame(['Failed', 'Painted'], array_column($response->json('paints'), 'status_label'));
        $this->assertSame('Executed successfully', $response->json('paints.1.code_label'));
    }

    public function test_without_a_login_it_asks_for_one(): void
    {
        $this->config->update(['username' => '']);

        $this->actingAs($this->user)->getJson(route('ekad.screen-status'))
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Set up Configuration & Login first.');
        Http::assertNothingSent();
    }

    public function test_the_ekad_page_has_the_screen_status_panel(): void
    {
        $this->actingAs($this->user)->get(route('ekad.index'))
            ->assertOk()
            ->assertSee('Screen Status')
            ->assertSee(route('ekad.screen-status'), false);
        Http::assertNothingSent();
    }
}
