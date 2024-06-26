<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRelationTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed();

        User::find(1)->followings()->toggle(User::find(2));
    }

    public function test_user1_has_one_following(): void
    {
        $user1 = User::find(1);
        $user2 = User::find(2);

        info($user1->followings()->toSql());

        $this->assertTrue($user1->followings->isNotEmpty());
        $this->assertTrue($user1->followings->contains($user2));
    }

    public function test_user1_has_two_followings(): void
    {
        $user1 = User::find(1);
        $user3 = User::find(3);

        $user1->followings()->toggle($user3);

        $this->assertSame(2, $user1->followings()->count());
        $this->assertTrue($user1->followings->contains($user3));
    }

    public function test_user1_has_no_followers(): void
    {
        $user1 = User::find(1);

        $this->assertTrue($user1->followers->isEmpty());
        $this->assertNull($user1->followers->last());
    }

    public function test_follow(): void
    {
        $user1 = User::find(1);
        $user3 = User::find(3);

        // attach/detachよりtoggleで切り換えたほうがいい
        $user1->followings()->attach($user3);

        $this->assertSame(2, $user1->followings->count());
        $this->assertTrue($user1->followings->contains($user3));
    }

    public function test_unfollow(): void
    {
        $user1 = User::find(1);
        $user2 = User::find(2);

        // attach/detachよりtoggleで切り換えたほうがいい
        $user1->followings()->attach($user2);
        // すでにフォローしているのにフォローすると重複するので。
        $this->assertSame(2, $user1->followings->count());
        // detachで両方とも削除されるけどdetachしない限り重複したまま。
        $this->assertSame(2, $user1->followings()->detach($user2));
        $user1->refresh();

        $this->assertSame(0, $user1->followings->count());
        $this->assertTrue($user1->followings->doesntContain($user2));
    }

    public function test_user2_has_one_follower(): void
    {
        $user1 = User::find(1);
        $user2 = User::find(2);

        $this->assertTrue($user2->followers->isNotEmpty());
        $this->assertSame(1, $user2->followers()->count());
        $this->assertTrue($user2->followers->contains($user1));
    }

    public function test_user3_has_one_follower_and_follower(): void
    {
        $user1 = User::find(1);
        $user2 = User::find(2);
        $user3 = User::find(3);

        $user1->followings()->toggle($user3);
        $user3->followings()->toggle($user1);

        $this->assertSame(1, $user3->followings->count());
        $this->assertSame(1, $user3->followers->count());
        $this->assertTrue($user3->followings->isNotEmpty());
        $this->assertTrue($user3->followers->isNotEmpty());
        $this->assertTrue($user3->followers->contains($user1));
        $this->assertTrue($user3->followings->contains($user1));
        $this->assertTrue($user3->followings->doesntContain($user2));
    }

    public function test_user1_has_statuses(): void
    {
        $user1 = User::find(1);

        $this->assertSame(10, $user1->statuses->count());
    }

    public function test_user1_timeline(): void
    {
        $user1 = User::find(1);

        $this->assertSame(10, $user1->timeline()->paginate(10)->count());
    }

    public function test_users_are_friends(): void
    {
        $user1 = User::find(1);
        $user2 = User::find(2);
        $user3 = User::find(3);

        $user2->followings()->toggle($user1);
        $user3->followings()->toggle($user1);

        info($user1->friends()->toSql());

        $this->assertSame(1, $user1->friends()->count());
        $this->assertSame(1, $user2->friends()->count());
        $this->assertSame(0, $user3->friends()->count());
        $this->assertTrue($user1->friends->contains($user2));
        $this->assertTrue($user1->friends->doesntContain($user3));
        $this->assertTrue($user2->friends->contains($user1));
        $this->assertTrue($user3->friends->doesntContain($user1));
        $this->assertTrue($user3->friends->doesntContain($user2));
    }

    public function test_user1_has_two_friends(): void
    {
        $user1 = User::find(1);
        $user2 = User::find(2);
        $user3 = User::find(3);

        $user2->followings()->toggle($user1);
        $user1->followings()->toggle($user3);
        $user3->followings()->toggle($user1);

        $this->assertSame(2, $user1->friends()->count());
        $this->assertTrue($user1->friends->contains($user2));
        $this->assertTrue($user1->friends->contains($user3));
    }

    public function test_user1_has_many_followers(): void
    {
        $user1 = User::factory()
            ->hasFollowers(1000)
            ->create();

        $this->assertSame(1000, $user1->followers->count());
        $this->assertSame(0, $user1->friends->count());
        $this->assertSame(3 + 1001, User::count());
    }

    public function test_pivot_is_follow_class(): void
    {
        $following = User::find(1)->followings()->first();
        $follower = User::find(2)->followers()->first();

        $this->assertInstanceOf(Follow::class, $following->pivot);
        $this->assertInstanceOf(Follow::class, $follower->pivot);
    }

    public function test_with_count(): void
    {
        $user1 = User::withCount(['followings', 'followers', 'friends', 'statuses'])->find(1);

        $user2 = User::find(2);
        $user2->loadCount(['followings', 'followers']);

        $this->assertSame(1, $user1->followings_count);
        $this->assertSame(0, $user1->followers_count);
        $this->assertSame(0, $user1->friends_count);
        $this->assertSame(10, $user1->statuses_count);
        $this->assertSame(0, $user2->followings_count);
        $this->assertSame(1, $user2->followers_count);
    }
}
