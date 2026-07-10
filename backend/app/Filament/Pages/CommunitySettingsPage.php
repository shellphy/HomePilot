<?php

namespace App\Filament\Pages;

use App\Settings\CommunitySettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * 社区设置：小区身份、产品文案、结构选项的可视化编辑页——交接后不需要碰任何代码。
 */
class CommunitySettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = '社区设置';

    protected static ?string $title = '社区设置';

    protected static ?string $slug = 'community-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(CommunitySettings $settings): void
    {
        $this->form->fill($settings->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('name')
                    ->label('小区名称')
                    ->required(),
                TextInput::make('app_name')
                    ->label('小程序名称（门户标题、分享文案用）')
                    ->required(),
                TextInput::make('slogan')
                    ->label('口号')
                    ->required(),
                TextInput::make('sub_slogan')
                    ->label('副口号')
                    ->required(),
                Textarea::make('pledge')
                    ->label('公益承诺（团购详情页、我的页底部展示）')
                    ->rows(2)
                    ->required(),
                Textarea::make('initiator_note')
                    ->label('团长规则声明（发起团购页展示）')
                    ->rows(3)
                    ->required(),
                TextInput::make('initiate_hint')
                    ->label('张罗引导语（小区页"我来张罗"卡片）')
                    ->required(),
                TextInput::make('data_footnote')
                    ->label('小区数据页脚注')
                    ->required(),
                TextInput::make('total_households')
                    ->label('小区总户数')
                    ->numeric()
                    ->required(),
                TagsInput::make('layouts')
                    ->label('户型列表（回车添加）')
                    ->required(),
                TagsInput::make('decoration_modes')
                    ->label('装修方式列表（回车添加）')
                    ->required(),
                TagsInput::make('categories')
                    ->label('团购品类列表（回车添加）')
                    ->required(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('保存')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ]);
    }

    public function save(CommunitySettings $settings): void
    {
        $settings->fill($this->form->getState());
        $settings->save();

        Notification::make()->title('已保存')->success()->send();
    }
}
