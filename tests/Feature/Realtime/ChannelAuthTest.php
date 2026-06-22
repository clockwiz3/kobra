<?php

namespace Tests\Feature\Realtime;

use App\Broadcasting\CoupleChannel;
use App\Models\User;
use App\Services\Couples\CoupleInvitationService;
use Database\Seeders\CoupleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Авторизация presence-канала couple.{id}. fail-closed по членству — security-инвариант
 * изоляции shard, поэтому здесь же leak-харнесс двух пар.
 */
class ChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_authorized_with_her_slot(): void
    {
        $this->seed(CoupleSeeder::class);
        $lesya = User::where('email', 'lesya@ix.app')->firstOrFail();
        $coupleId = $lesya->activeCouple()->id;

        $res = (new CoupleChannel())->join($lesya, $coupleId);

        $this->assertIsArray($res);
        $this->assertSame('her', $res['slot']); // owner → her
        $this->assertSame($lesya->id, $res['id']);
        $this->assertSame('Леся', $res['name']);
    }

    public function test_partner_authorized_with_him_slot(): void
    {
        $this->seed(CoupleSeeder::class);
        $seryozha = User::where('email', 'seryozha@ix.app')->firstOrFail();
        $coupleId = $seryozha->activeCouple()->id;

        $res = (new CoupleChannel())->join($seryozha, $coupleId);

        $this->assertIsArray($res);
        $this->assertSame('him', $res['slot']); // partner → him
    }

    public function test_non_member_denied(): void
    {
        $this->seed(CoupleSeeder::class);
        $lesya = User::where('email', 'lesya@ix.app')->firstOrFail();
        $coupleId = $lesya->activeCouple()->id;
        $stranger = User::factory()->create();

        $this->assertFalse((new CoupleChannel())->join($stranger, $coupleId));
    }

    /** Leak-харнесс: член пары B не авторизуется на канал пары A, но авторизуется на свой. */
    public function test_other_couple_member_denied_for_foreign_channel(): void
    {
        $this->seed(CoupleSeeder::class);
        $coupleA = User::where('email', 'lesya@ix.app')->firstOrFail()->activeCouple()->id;

        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $invitations = app(CoupleInvitationService::class);
        $coupleB = $invitations->accept($invitations->invite($owner, $partner->email), $partner);

        // Изоляция shard: на чужой канал — нельзя.
        $this->assertFalse((new CoupleChannel())->join($owner, $coupleA));
        // На свой — можно.
        $this->assertIsArray((new CoupleChannel())->join($owner, $coupleB->id));
    }
}
