<?php

namespace App\Filament\Resources;

use Carbon\Carbon;
use Filament\Forms;
use Filament\Tables;
use App\Models\Expense;
use Filament\Forms\Form;
use App\Enum\ExpenseType;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Resources\Resource;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Actions\ActionGroup;
use App\Filament\Forms\Fields\MoneyField;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\ExpenseResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Filament\Resources\ExpenseResource\RelationManagers;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-down';

    protected static ?string $navigationGroup = 'Transaksi';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('category')
                    ->options(ExpenseType::options())
                    ->label('Kategori Pengeluaran')
                    ->searchable()
                    ->required(),
                MoneyField::make(null, 'amount', 'Jumlah Pengeluaran'),
                Forms\Components\DatePicker::make('expense_date')
                    ->label('Tanggal Pengeluaran')
                    ->required()
                    ->default(now())
                    ->maxDate(now()),
                Forms\Components\TextArea::make('description')
                    ->label('Deskripsi')
                    ->required(),

                SpatieMediaLibraryFileUpload::make('receipt_number')
                    ->image()
                    ->collection('receipt')
                    ->disk('public')
                    ->directory('receipt')
                    ->visibility('public')
                    ->maxSize(2048)
                    ->helperText('Max size 2MB')
                    ->rules(['image', 'max:2048'])
                    ->validationMessages([
                        'image' => 'File yang diunggah harus gambar.',
                        'max' => 'File yang diunggah tidak boleh lebih besar dari 2MB.',
                    ])
                    ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file, Expense $record): string {
                        $filename = $record->id . Str::random(10) . '.' . $file->getClientOriginalExtension();
                        return $filename;
                    }),

                Hidden::make('user_id')
                    ->default(auth()->user()->id),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('receipt_number')->collection('receipt')
                    ->label('Nota'),
                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->formatStateUsing(fn($state) => ExpenseType::from($state)->getLabel())
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Tanggal Pengeluaran')
                    ->formatStateUsing(fn($state) => Carbon::parse($state)->translatedFormat('d F Y'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Dibuat Oleh')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make()->color('primary'),
                    Tables\Actions\EditAction::make(),
                ])
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->size('xl'),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'view' => Pages\ViewExpense::route('/{record}'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Pengeluaran';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Pengeluaran';
    }
}
