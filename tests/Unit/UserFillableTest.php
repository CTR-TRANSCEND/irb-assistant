<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * M1: Defense-in-depth — approval fields must NOT be mass-assignable.
 *
 * RegisteredUserController hardcodes is_approved=false; admin approve/reject
 * paths use direct attribute assignment (not fill/create with untrusted input).
 * Keeping these fields in $fillable creates an unnecessary mass-assignment
 * vector even though there is no current exploit path.
 */
final class UserFillableTest extends TestCase
{
    #[Test]
    public function is_approved_is_not_in_fillable(): void
    {
        $this->assertNotContains('is_approved', (new User)->getFillable());
    }

    #[Test]
    public function approved_at_is_not_in_fillable(): void
    {
        $this->assertNotContains('approved_at', (new User)->getFillable());
    }

    #[Test]
    public function approved_by_is_not_in_fillable(): void
    {
        $this->assertNotContains('approved_by', (new User)->getFillable());
    }
}
