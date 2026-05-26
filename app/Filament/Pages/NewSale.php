<?php

namespace App\Filament\Pages;

use App\Exceptions\InsufficientStockException;
use App\Filament\Resources\SaleInvoiceResource;
use App\Models\Product;
use App\Services\SaleInvoiceService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Throwable;

class NewSale extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'New Sale';

    protected string $view = 'filament.pages.new-sale';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'invoice_number' => 'SALE-' . now()->format('YmdHis'),
            'invoice_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'customer_name' => null,
            'customer_phone' => null,
            'discount' => 0,
            'tax' => 0,
            'items' => [
                [
                    'product_id' => null,
                    'quantity' => 1,
                    'unit_price' => null,
                ],
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sale details')
                    ->description('Customer, payment, and invoice summary.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Invoice number')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\DatePicker::make('invoice_date')
                            ->label('Invoice date')
                            ->required()
                            ->default(now()),

                        Forms\Components\Select::make('payment_method')
                            ->label('Payment method')
                            ->required()
                            ->options([
                                'cash' => 'Cash',
                                'card' => 'Card',
                                'transfer' => 'Bank transfer',
                            ])
                            ->native(false),

                        Forms\Components\TextInput::make('customer_name')
                            ->label('Customer name')
                            ->placeholder('Walk-in customer')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('customer_phone')
                            ->label('Customer phone')
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('discount')
                            ->numeric()
                            ->step('0.01')
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\TextInput::make('tax')
                            ->numeric()
                            ->step('0.01')
                            ->default(0)
                            ->minValue(0),
                    ]),

                Section::make('Sale items')
                    ->description('Stock will be consumed automatically using FEFO after saving.')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('Items')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->options(fn () => Product::query()
                                        ->where('is_active', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id'))
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (?int $state, Set $set): void {
                                        $price = Product::query()->whereKey($state)->value('sale_price');

                                        if ($price !== null) {
                                            $set('unit_price', $price);
                                        }
                                    })
                                    ->required()
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Unit price')
                                    ->numeric()
                                    ->step('0.01')
                                    ->minValue(0)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->addActionLabel('Add item')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }



    public function cancel(): void
    {
        $this->redirect(SaleInvoiceResource::getUrl('index'));
    }

    public function save(): void
    {
        $state = $this->form->getState();

        try {
            $invoice = app(SaleInvoiceService::class)->create(
                data: $state,
                items: $state['items'] ?? [],
            );

            Notification::make()
                ->title("Sale {$invoice->invoice_number} completed")
                ->body('Stock was consumed using FEFO.')
                ->success()
                ->send();

            $this->redirect(SaleInvoiceResource::getUrl('view', ['record' => $invoice]));
        } catch (InsufficientStockException $exception) {
            Notification::make()
                ->title('Insufficient stock')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Sale failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }
}
