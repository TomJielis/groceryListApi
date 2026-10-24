<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardTest extends TestCase
{
    use RefreshDatabase;

    private function card(User $user, string $title): Card
    {
        return Card::create(['user_id' => $user->id, 'title' => $title, 'attachment' => 'data:image/png;base64,AAAA']);
    }

    public function test_index_returns_only_the_users_own_cards(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->card($user, 'Bonuskaart');
        $this->card($other, 'Van een ander');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/cards/index')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Bonuskaart');
    }

    public function test_store_needs_a_title_and_an_attachment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/cards/store', ['title' => 'Zonder foto'])->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->postJson('/api/cards/store', ['attachment' => 'data:'])->assertUnprocessable();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/cards/store', ['title' => 'Bonuskaart', 'attachment' => 'data:image/png;base64,AAAA'])
            ->assertOk()
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertSame(1, Card::count());
    }

    public function test_a_user_deletes_their_own_card(): void
    {
        $user = User::factory()->create();
        $card = $this->card($user, 'Bonuskaart');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/cards/{$card->id}/delete")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('cards', ['id' => $card->id]);
    }

    public function test_nobody_else_can_delete_a_card(): void
    {
        $owner = User::factory()->create();
        $card = $this->card($owner, 'Bonuskaart');

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/cards/{$card->id}/delete")
            ->assertForbidden();

        $this->assertDatabaseHas('cards', ['id' => $card->id]);
    }

    public function test_the_card_endpoints_need_a_login(): void
    {
        $card = $this->card(User::factory()->create(), 'Bonuskaart');

        $this->getJson('/api/cards/index')->assertUnauthorized();
        $this->postJson('/api/cards/store', ['title' => 'X', 'attachment' => 'data:'])->assertUnauthorized();
        $this->deleteJson("/api/cards/{$card->id}/delete")->assertUnauthorized();
        $this->assertDatabaseHas('cards', ['id' => $card->id]);
    }
}
