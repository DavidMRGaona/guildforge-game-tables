<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\CampaignResponseDTO;
use Modules\GameTables\Application\DTOs\CreationEligibilityDTO;
use Modules\GameTables\Application\DTOs\FrontendCreateCampaignDTO;
use Modules\GameTables\Application\DTOs\FrontendCreateGameTableDTO;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\DTOs\MyPublishedTableDTO;

interface FrontendCreationServiceInterface
{
    /**
     * Create a new game table from the frontend.
     * The table will be created in Draft or PendingReview status depending on config.
     * A campaign session outside an event is also created for someone who directs its campaign
     * when the general creation rules refuse them.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\NotEligibleToCreateException
     * @throws \Modules\GameTables\Domain\Exceptions\SessionBelowRosterException when it would be published at once
     *         with fewer seats than its campaign roster (nothing is saved)
     */
    public function createGameTable(FrontendCreateGameTableDTO $dto): GameTableResponseDTO;

    /**
     * Create a new campaign from the frontend.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\NotEligibleToCreateException
     */
    public function createCampaign(FrontendCreateCampaignDTO $dto): CampaignResponseDTO;

    /**
     * Get data needed to render the creation form (game systems, formats, etc.). The campaigns are
     * the ones the current user may create sessions of, plus $keepCampaignId (the campaign stored on
     * a table being edited) when it is not among them. The events are the open ones, plus
     * $keepEventId (the event stored on a table being edited, which may have closed since).
     *
     * @return array{
     *     game_systems: array<array{id: string, name: string}>,
     *     table_types: array<string, string>,
     *     table_formats: array<string, string>,
     *     experience_levels: array<string, string>,
     *     tones: array<string, string>,
     *     character_creation: array<string, string>,
     *     safety_tools: array<string, string>,
     *     content_warnings: array<array{id: string, name: string, description: string|null, severity: string}>,
     *     genres: array<string, string>,
     *     languages: array<string, string>,
     *     campaign_frequencies: array<string, string>,
     *     events: list<array{id: string, name: string, start_date: string}>,
     *     campaigns: list<array{id: string, name: string, game_system_id: string, max_players: int|null, roster_count: int}>,
     *     defaults: array{duration_minutes: int, min_players: int, max_players: int, max_spectators: int},
     *     limits: array{max_players: int, max_spectators: int, max_duration_minutes: int}
     * }
     */
    public function getCreateFormData(?string $keepCampaignId = null, ?string $keepEventId = null): array;

    /**
     * Ids of the events a table created or edited from the web may be linked to: the ones the
     * creation form offers (published, not ended, with tables enabled).
     *
     * @return list<string>
     */
    public function selectableEventIds(): array;

    /**
     * Whether the user may create a new table for the event, for the create page and the event
     * page: the event's creation rules, and the event must be one the form offers (published, not
     * ended); otherwise event_not_open (an event without tables keeps tables_not_enabled_for_event).
     * Editing does not use it: a table keeps an event that closed after it was linked.
     */
    public function eventCreationEligibility(string $eventId, ?string $userId): CreationEligibilityDTO;

    /**
     * Get all drafts created by a user.
     *
     * @return array<GameTableResponseDTO>
     */
    public function getUserDrafts(string $userId): array;

    /**
     * What «Mis mesas» lists: the person's drafts, and the published single tables (from 30 days ago
     * onwards) they created or direct, by start date.
     *
     * @return array{drafts: list<GameTableResponseDTO>, published: list<MyPublishedTableDTO>}
     */
    public function getMyTables(string $userId): array;

    /**
     * Submit a draft table for review.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\TableNotFoundException
     * @throws \Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException
     * @throws \Modules\GameTables\Domain\Exceptions\NotEligibleToCreateException when the author may not create it any more
     * @throws \Modules\GameTables\Domain\Exceptions\SessionBelowRosterException when it would be published at once
     */
    public function submitForReview(string $tableId, string $userId): void;

    /**
     * Update a draft table.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\TableNotFoundException
     * @throws \Modules\GameTables\Domain\Exceptions\NotEditableException
     * @throws \Modules\GameTables\Domain\Exceptions\NotEligibleToCreateException when the author may not create the edited table
     */
    public function updateDraft(string $tableId, FrontendCreateGameTableDTO $dto): GameTableResponseDTO;

    /**
     * The directors of a draft as its author edits them on the web: the author as main director
     * (their own record on the table, or a new row from their account) and the co-directors without
     * an account, in order. Directors with another account (only staff link them) are left out and
     * kept as they are on save.
     *
     * @return list<array{id: string|null, user_id: string|null, first_name: string, last_name: string, email: string, phone: string, custom_title: string, is_name_public: bool, role: string}>
     */
    public function getDraftGameMasters(string $tableId, string $authorId): array;

    /**
     * Ids of the table's co-directors without an account: the only rows of the web form that keep
     * their id on save. A co-director row with any other id (or none) is a new co-director.
     *
     * @return list<string>
     */
    public function editableCoDirectorIds(string $tableId): array;

    /**
     * The author's own values as main director, kept on the director record every table they
     * direct shares (defaults when they have none yet): the web form pre-fills them.
     *
     * @return array{phone: string, custom_title: string, is_name_public: bool}
     */
    public function getAuthorGameMasterProfile(string $authorId): array;

    /**
     * Delete a draft table.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\TableNotFoundException
     * @throws \Modules\GameTables\Domain\Exceptions\NotEditableException
     */
    public function deleteDraft(string $tableId, string $userId): void;

    /**
     * Approve a frontend-created table for publication.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\GameTableNotFoundException
     * @throws \Modules\GameTables\Domain\Exceptions\NotEditableException when it is not waiting for review
     * @throws \Modules\GameTables\Domain\Exceptions\SessionBelowRosterException
     */
    public function approveFrontendCreation(string $tableId, ?string $notes = null): void;

    /**
     * Reject a frontend-created table.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\GameTableNotFoundException
     * @throws \Modules\GameTables\Domain\Exceptions\NotEditableException when it is not waiting for review
     */
    public function rejectFrontendCreation(string $tableId, string $reason): void;

    /**
     * Get all campaign drafts created by a user.
     *
     * @return array<CampaignResponseDTO>
     */
    public function getUserCampaignDrafts(string $userId): array;

    /**
     * The campaigns shown in «Mis campañas»: the person's own web drafts plus every approved or
     * published campaign they direct (finished ones included), newest first.
     *
     * @return list<CampaignResponseDTO>
     */
    public function getMyCampaigns(string $userId): array;

    /**
     * Update a draft campaign.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\CampaignNotFoundException
     * @throws \Modules\GameTables\Domain\Exceptions\NotEditableException
     */
    public function updateCampaignDraft(string $campaignId, FrontendCreateCampaignDTO $dto): CampaignResponseDTO;

    /**
     * Submit a draft campaign for review.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\CampaignNotFoundException
     * @throws \Modules\GameTables\Domain\Exceptions\NotEditableException
     */
    public function submitCampaignForReview(string $campaignId, string $userId): void;

    /**
     * Approve a campaign sent for review and publish it.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\CampaignNotFoundException
     * @throws \Modules\GameTables\Domain\Exceptions\NotEditableException when it is not waiting for review
     */
    public function approveCampaign(string $campaignId, ?string $notes = null): void;

    /**
     * Reject a campaign sent for review.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\CampaignNotFoundException
     * @throws \Modules\GameTables\Domain\Exceptions\NotEditableException when it is not waiting for review
     */
    public function rejectCampaign(string $campaignId, string $reason): void;

    /**
     * Delete a draft campaign.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\CampaignNotFoundException
     * @throws \Modules\GameTables\Domain\Exceptions\NotEditableException
     */
    public function deleteCampaignDraft(string $campaignId, string $userId): void;
}
