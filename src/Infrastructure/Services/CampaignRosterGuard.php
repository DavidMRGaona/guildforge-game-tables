<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Modules\GameTables\Application\Services\AssociationRoleNameInterface;
use Modules\GameTables\Application\Services\MembershipCheckerInterface;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Enums\RosterAccess;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;

final readonly class CampaignRosterGuard
{
    /** Message prefix of the newcomer refusals for the direction sending an invitation */
    public const string INVITER = 'invite_';

    /** Message prefix of the newcomer refusals for the invitee accepting it */
    public const string INVITEE = 'invitee_';

    /** Message prefix of the newcomer refusals for the direction accepting a request */
    public const string ACCEPTER = 'accept_';

    public function __construct(
        private CampaignRosterRepositoryInterface $roster,
        private MembershipCheckerInterface $membership,
        private AssociationRoleNameInterface $associationRole,
    ) {}

    public function ensureCanChange(Campaign $campaign): void
    {
        if (! $campaign->isApprovedOrPublished()) {
            throw RosterActionRefusedException::because('unavailable');
        }

        if ($campaign->isFinished()) {
            throw RosterActionRefusedException::because('finished');
        }
    }

    /**
     * Counted under the campaign lock, so two changes never take the last place.
     */
    public function ensureRoomIn(Campaign $campaign): void
    {
        if (! $campaign->hasRoomFor($this->roster->count($campaign->id))) {
            throw RosterActionRefusedException::because('roster_full');
        }
    }

    /**
     * What a newcomer meets, whether asking to join or invited: the campaign takes new players and,
     * on a members-only roster, the person is an active member. Without a user (an email with no
     * account yet) only the campaign's own rule is read.
     *
     * @return 'not_accepting'|'members_only'|null
     */
    public function newcomerRefusal(Campaign $campaign, ?string $userId): ?string
    {
        if (! $campaign->canAcceptNewPlayers()) {
            return 'not_accepting';
        }

        if ($userId !== null && $campaign->rosterAccess === RosterAccess::MembersOnly && ! $this->membership->isActiveMember($userId)) {
            return 'members_only';
        }

        return null;
    }

    /**
     * @param  string  $copy  the reader's message prefix (self::INVITER, self::INVITEE), none for a request
     *
     * @throws RosterActionRefusedException not_accepting or members_only
     */
    public function ensureAdmitsNewcomer(Campaign $campaign, ?string $userId, string $copy = ''): void
    {
        $reason = $this->newcomerRefusal($campaign, $userId);

        if ($reason !== null) {
            throw $this->refusal($reason, $copy);
        }
    }

    /**
     * The refusal of ensureAdmitsNewcomer() as its reader would get it, for pages that show it
     * before anyone acts.
     */
    public function newcomerRefusalMessage(Campaign $campaign, ?string $userId, string $copy = ''): ?string
    {
        $reason = $this->newcomerRefusal($campaign, $userId);

        return $reason === null ? null : $this->refusal($reason, $copy)->getMessage();
    }

    /** The members-only messages name the site's association member role */
    private function refusal(string $reason, string $copy): RosterActionRefusedException
    {
        return RosterActionRefusedException::because($reason, ['role' => $this->associationRole->displayName()], $copy);
    }
}
