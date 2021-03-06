<?php

namespace Tests\Browser;

use App\Dock;
use App\Ship;
use App\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Components\IndexComponent;
use Tests\DuskTestCase;

class CreateWithSoftDeletingBelongsToTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * @test
     */
    public function test_parent_select_is_locked_when_creating_child_of_soft_deleted_resource()
    {
        $this->seed();

        $dock = factory(Dock::class)->create(['deleted_at' => now()]);

        $this->browse(function (Browser $browser) use ($dock) {
            $browser->loginAs(User::find(1))
                    ->visit(new Pages\Detail('docks', $dock->id))
                    ->within(new IndexComponent('ships'), function ($browser) {
                        $browser->click('@create-button');
                    })
                    ->on(new Pages\Create('ships'))
                    ->assertDisabled('@dock')
                    ->type('@name', 'Test Ship')
                    ->create();

            $this->assertCount(1, $dock->fresh()->ships);
        });
    }

    /**
     * @test
     */
    public function non_searchable_belongs_to_respects_with_trashed_checkbox_state()
    {
        $this->seed();

        $ship = factory(Ship::class)->create(['deleted_at' => now()]);
        $ship2 = factory(Ship::class)->create();

        $this->browse(function (Browser $browser) use ($ship, $ship2) {
            $browser->loginAs(User::find(1))
                    ->visit(new Pages\Create('sails'))
                    ->assertSelectMissingOption('@ship', $ship->id)
                    ->assertSelectHasOption('@ship', $ship2->id)
                    ->withTrashedRelation('ships')
                    ->assertSelectHasOption('@ship', $ship->id)
                    ->assertSelectHasOption('@ship', $ship2->id)
                    ->select('@ship', $ship->id)
                    ->type('@inches', 25)
                    ->create();

            $this->assertCount(1, $ship->fresh()->sails);
        });
    }

    /**
     * @test
     */
    public function unable_to_uncheck_with_trashed_if_currently_selected_non_searchable_parent_is_trashed()
    {
        $this->seed();

        $ship = factory(Ship::class)->create(['deleted_at' => now()]);

        $this->browse(function (Browser $browser) use ($ship) {
            $browser->loginAs(User::find(1))
                    ->visit(new Pages\Create('sails'))
                    ->withTrashedRelation('ships')
                    ->select('@ship', $ship->id)
                    ->withoutTrashedRelation('ships')
                    // Ideally would use assertChecked here but RemoteWebDriver
                    // returns unchecked when it clearly is checked?
                    ->type('@inches', 25)
                    ->create();

            $this->assertCount(1, $ship->fresh()->sails);
        });
    }

    /**
     * @test
     */
    public function searchable_belongs_to_respects_with_trashed_checkbox_state()
    {
        $this->whileSearchable(function () {
            $this->seed();

            $dock = factory(Dock::class)->create(['deleted_at' => now()]);

            $this->browse(function (Browser $browser) use ($dock) {
                $browser->loginAs(User::find(1))
                        ->visit(new Pages\Create('ships'))
                        ->searchRelation('docks', '1')
                        ->assertNoRelationSearchResults('docks')
                        ->withTrashedRelation('docks')
                        ->searchRelation('docks', '1')
                        ->selectCurrentRelation('docks')
                        ->type('@name', 'Test Ship')
                        ->create();

                $this->assertCount(1, $dock->fresh()->ships);
            });
        });
    }
}
