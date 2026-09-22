<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Api\Controllers\PreviewAcceptController;
use FlatRate\WikiContext\Preview\PreviewAcceptanceRepository;
use FlatRate\WikiContext\Preview\PreviewAcceptanceService;
use FlatRate\WikiContext\Preview\PreviewAudienceFactory;
use FlatRate\WikiContext\Preview\PreviewAuthorization;
use FlatRate\WikiContext\Preview\PreviewFixtureCatalog;
use FlatRate\WikiContext\Preview\PreviewFixtureRunner;
use FlatRate\WikiContext\Preview\RuntimeBindingAuthority;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;

final class PreviewAcceptControllerTest extends TestCase
{
    private const COMMUNITY = '11111111-1111-4111-8111-111111111111';
    private const GRAPH = '22222222-2222-4222-8222-222222222222';

    private ConnectionInterface $db;

    protected function setUp(): void
    {
        parent::setUp();
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $capsule->setAsGlobal();
        $this->db = $capsule->getConnection();
        $schema = $this->db->getSchemaBuilder();
        $schema->create('flatrate_wiki_projection_state', function (Blueprint $table) {
            $table->char('community_uuid', 36)->primary();
            $table->char('active_graph_version_uuid', 36);
        });
        $schema->create('flatrate_wiki_preview_acceptance', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('accepted_by_user_id');
            $table->dateTime('accepted_at');
            $table->char('active_graph_version_uuid', 36);
            $table->string('extension_build_id', 128);
            $table->string('wiki_contract_version', 64);
            $table->string('directory_display_policy_digest', 128);
            $table->string('audience_profiles_verified', 255);
            $table->string('fixture_results_digest', 128);
            $table->string('status', 32);
        });
        $this->db->table('flatrate_wiki_projection_state')->insert([
            'community_uuid' => self::COMMUNITY,
            'active_graph_version_uuid' => self::GRAPH,
        ]);
    }

    public function test_non_admin_is_denied(): void
    {
        $response = $this->controller(true)->handle(
            $this->request($this->actor(9, false))
        );
        $this->assertSame(403, $response->getStatusCode());
        echo "ADMIN_REQUIRED=PASS\n";
    }

    public function test_admin_accept_success_path(): void
    {
        $response = $this->controller(true)->handle(
            $this->request($this->actor(1, true))
        );
        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertFalse($body['same_user_component_path_claimed']);
        $this->assertSame('PASS', $body['receipt']['status']);
        $this->assertSame(1, $this->db->table('flatrate_wiki_preview_acceptance')->count());
    }

    public function test_accept_fails_closed_when_preview_gate_off(): void
    {
        $response = $this->controller(false)->handle(
            $this->request($this->actor(1, true))
        );
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(0, $this->db->table('flatrate_wiki_preview_acceptance')->count());
    }

    private function controller(bool $preview): PreviewAcceptController
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->method('get')->willReturnCallback(
            fn (string $key, $default = null) => $key === FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED
                ? ($preview ? '1' : '0')
                : $default
        );

        $service = new PreviewAcceptanceService(
            new PreviewAuthorization(),
            $settings,
            new RuntimeBindingAuthority($this->db),
            new PreviewFixtureRunner(
                PreviewFixtureCatalog::fromDefaultFixtureFile(),
                new PreviewAudienceFactory()
            ),
            new PreviewAcceptanceRepository($this->db)
        );

        return new PreviewAcceptController($settings, new PreviewAuthorization(), $service);
    }

    private function actor(int $id, bool $admin): User
    {
        $actor = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isAdmin'])
            ->getMock();
        $actor->id = $id;
        $actor->method('isAdmin')->willReturn($admin);

        return $actor;
    }

    private function request(User $actor)
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/flatrate-wiki/preview/accept');

        return RequestUtil::withActor($request, $actor);
    }
}
