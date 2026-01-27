<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameTableResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Filament\Resources\GameTableResource;

final class EditGameTable extends EditRecord
{
    protected static string $resource = GameTableResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('game-tables::messages.pages.edit_table');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label(__('game-tables::messages.actions.publish'))
                ->icon('heroicon-o-eye')
                ->color('success')
                ->visible(fn (): bool => ! $this->record->is_published)
                ->action(function (): void {
                    $this->record->update([
                        'is_published' => true,
                        'status' => TableStatus::Published->value,
                        'published_at' => now(),
                    ]);
                    $this->refreshFormData(['is_published', 'status', 'published_at']);
                }),

            Action::make('open_registration')
                ->label(__('game-tables::messages.actions.open_registration'))
                ->icon('heroicon-o-clipboard-document-check')
                ->color('info')
                ->visible(fn (): bool => $this->record->status === TableStatus::Published)
                ->action(function (): void {
                    $this->record->update(['status' => TableStatus::Open->value]);
                    $this->refreshFormData(['status']);
                }),

            Action::make('cancel')
                ->label(__('game-tables::messages.actions.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => ! in_array($this->record->status, [TableStatus::Cancelled, TableStatus::Completed]))
                ->action(function (): void {
                    $this->record->update(['status' => TableStatus::Cancelled->value]);
                    $this->refreshFormData(['status']);
                }),

            DeleteAction::make(),
        ];
    }
}
