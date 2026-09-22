<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Preview\PreviewAcceptance;
use FlatRate\WikiContext\Preview\PreviewAcceptanceRepository;
use FlatRate\WikiContext\Preview\PreviewAcceptanceService;
use FlatRate\WikiContext\Preview\PreviewAudienceFactory;
use FlatRate\WikiContext\Preview\PreviewAuthorization;
use FlatRate\WikiContext\Preview\PreviewFixtureCatalog;
use FlatRate\WikiContext\Preview\PreviewFixtureRunner;
use FlatRate\WikiContext\Preview\RuntimeBindingAuthority;
use FlatRate\WikiContext\Support\DirectoryDisplayPolicy;
use FlatRate\WikiContext\Support\FeatureGates;
use FlatRate\WikiContext\Support\PublicRolloutPolicy;
use FlatRate\WikiContext\Support\WikiBuild;
use FlatRate\WikiContext\Support\WikiContract;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PreviewAcceptanceServiceTest extends TestCase
{
    private const COMMUNITY = '11111111-1111-4111-8111-111111111111';
    private const GRAPH = '22222222-2222-4222-8222-222222222222';

    private Capsule $capsule;
    private ConnectionInterface $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->capsule = new Capsule();
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->capsule->setAsGlobal();
        $this->db = $this->capsule->getConnection();
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

    public function test_admin_acceptance_persists_bound_pass_receipt(): void
    {
        $service = $this->service(true);
        $result = $service->accept($this->actor(7, true));
        $receipt = $result['receipt'];

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['same_user_component_path_claimed']);
        $this->assertSame('PASS', $receipt['status']);
        $this->assertSame(7, $receipt['accepted_by_user_id']);
        $this->assertSame(self::GRAPH, $receipt['active_graph_version_uuid']);
        $this->assertSame(WikiBuild::BUILD_ID, $receipt['extension_build_id']);
        $this->assertSame(WikiContract::VERSION, $receipt['wiki_contract_version']);
        $this->assertSame(DirectoryDisplayPolicy::digest(), $receipt['directory_display_policy_digest']);
        $this->assertSame('guest,standard_member', $receipt['audience_profiles_verified']);
        $this->assertSame(64, strlen($receipt['fixture_results_digest']));

        $loaded = (new PreviewAcceptanceRepository($this->db))->findLatestPass();
        $this->assertNotNull($loaded);
        $this->assertTrue(PreviewAcceptance::isFresh($loaded, (new RuntimeBindingAuthority($this->db))->current()));
        $this->assertTrue(PublicRolloutPolicy::allows($loaded, (new RuntimeBindingAuthority($this->db))->current()));

        echo "ADMIN_REQUIRED=PASS\n";
        echo "RECEIPT_PERSISTENCE=PASS\n";
        echo "RECEIPT_BINDS_GRAPH=PASS\n";
        echo "RECEIPT_BINDS_BUILD=PASS\n";
        echo "RECEIPT_BINDS_CONTRACT=PASS\n";
        echo "RECEIPT_BINDS_DISPLAY_POLICY=PASS\n";
    }

    public function test_non_admin_cannot_accept(): void
    {
        $service = $this->service(true);
        $this->expectException(PermissionDeniedException::class);
        $service->accept($this->actor(42, false));
    }

    public function test_accept_fails_closed_without_preview_gate(): void
    {
        $service = $this->service(false);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('wiki_preview_closed');
        $service->accept($this->actor(1, true));
    }

    public function test_accept_fails_closed_without_active_graph_and_does_not_insert(): void
    {
        $this->db->table('flatrate_wiki_projection_state')->delete();
        $service = $this->service(true);
        try {
            $service->accept($this->actor(1, true));
            $this->fail('missing active graph must fail');
        } catch (RuntimeException $e) {
            $this->assertSame('preview_accept_no_active_graph', $e->getMessage());
        }
        $this->assertSame(0, $this->db->table('flatrate_wiki_preview_acceptance')->count());
    }

    private function service(bool $previewEnabled): PreviewAcceptanceService
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->method('get')->willReturnCallback(
            fn (string $key, $default = null) => $key === FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED
                ? ($previewEnabled ? '1' : '0')
                : $default
        );

        return new PreviewAcceptanceService(
            new PreviewAuthorization(),
            $settings,
            new RuntimeBindingAuthority($this->db),
            new PreviewFixtureRunner(
                PreviewFixtureCatalog::fromDefaultFixtureFile(),
                new PreviewAudienceFactory()
            ),
            new PreviewAcceptanceRepository($this->db)
        );
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
}
