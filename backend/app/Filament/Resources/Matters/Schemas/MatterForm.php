<?php

namespace App\Filament\Resources\Matters\Schemas;

use App\Matters\MatterTypeRegistry;
use App\Settings\CommunitySettings;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class MatterForm
{
    public static function configure(Schema $schema): Schema
    {
        $typeOptions = collect(MatterTypeRegistry::keys())
            ->mapWithKeys(fn (string $key): array => [$key => MatterTypeRegistry::for($key)->label()])
            ->all();

        // 状态选项取全类型并集：管理端从简，状态与类型的匹配由 API 层严格校验
        $stateOptions = collect(MatterTypeRegistry::keys())
            ->flatMap(fn (string $key): array => MatterTypeRegistry::for($key)->states())
            ->all();

        return $schema
            ->components([
                Select::make('type')
                    ->label('类型')
                    ->options($typeOptions)
                    ->default('groupbuy')
                    ->required(),
                TextInput::make('title')
                    ->label('标题')
                    ->placeholder('如：「城建装饰」整装团购')
                    ->required(),
                TextInput::make('category')
                    ->label('品类（团购）')
                    ->datalist(fn (): array => app(CommunitySettings::class)->categories)
                    ->default(''),
                Select::make('state')
                    ->label('状态')
                    ->options($stateOptions)
                    ->default('seeking')
                    ->required(),
                Toggle::make('is_approved')
                    ->label('审核通过（对业主展示）')
                    ->helperText('业主发起的事务默认待审核，打开后才会出现在小程序的小区页里'),
                TextInput::make('target_count')
                    ->label('目标户数（团购）')
                    ->numeric()
                    ->default(0),
                Textarea::make('payload.body')
                    ->label('公告正文（仅公告类型）')
                    ->rows(4)
                    ->columnSpanFull(),
                Textarea::make('payload.pitch')
                    ->label('团长的话')
                    ->rows(4)
                    ->placeholder('自家已签约、条件对每户一样、权益公开……写给邻居看的话')
                    ->columnSpanFull(),
                TextInput::make('payload.perk')
                    ->label('阶梯优惠')
                    ->placeholder('如：满 20 户赠全屋水电升级')
                    ->default(''),
                Repeater::make('payload.terms')
                    ->label('团购条件')
                    ->schema([
                        TextInput::make('label')->label('名目')->required(),
                        TextInput::make('value')->label('内容')->required(),
                    ])
                    ->columns(2)
                    ->default([])
                    ->columnSpanFull(),
                Repeater::make('payload.glossary')
                    ->label('买前必懂（3~5 条，大白话）')
                    ->schema([
                        TextInput::make('term')->label('术语')->required(),
                        Textarea::make('explain')->label('大白话解释 + 对决策的影响')->rows(2)->required(),
                    ])
                    ->default([])
                    ->columnSpanFull(),
                Repeater::make('payload.final_terms')
                    ->label('成交公示 · 最终条件（成团后）')
                    ->schema([
                        TextInput::make('label')->label('名目')->required(),
                        TextInput::make('value')->label('内容')->required(),
                    ])
                    ->columns(2)
                    ->default([])
                    ->columnSpanFull(),
                Textarea::make('payload.final_note')
                    ->label('成交说明（返点让利去向）')
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('payload.collects_contact')
                    ->label('征集联系方式（仅征集类型）')
                    ->helperText('打开后，参与者第一次提交时需要填写楼栋号和微信号'),
                Repeater::make('payload.modules')
                    ->label('征集问卷（仅征集类型）')
                    ->helperText('按模块组织题目；小程序端按模块分步作答，聚合结果自动公示在小区数据页')
                    ->schema([
                        TextInput::make('title')->label('模块标题')->required(),
                        TextInput::make('intro')->label('模块引言（选填）'),
                        Repeater::make('questions')
                            ->label('题目')
                            ->schema([
                                TextInput::make('text')->label('题目')->required()->columnSpanFull(),
                                Select::make('type')
                                    ->label('题型')
                                    ->options(['single' => '单选', 'multi' => '多选'])
                                    ->default('single')
                                    ->required(),
                                Toggle::make('required')->label('必答')->inline(false),
                                TagsInput::make('options')
                                    ->label('选项（回车添加一项）')
                                    ->required()
                                    ->columnSpanFull(),
                                TextInput::make('key')
                                    ->label('题目标识')
                                    ->helperText('答案按它存储——发布收到答案后不要再改')
                                    ->default(fn (): string => 'q_'.Str::lower(Str::random(6)))
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->collapsible(),
                    ])
                    ->default([])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }
}
