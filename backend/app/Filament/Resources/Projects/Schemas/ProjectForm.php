<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectStatus;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('category')
                    ->label('品类')
                    ->datalist(config('homepilot.categories'))
                    ->required(),
                TextInput::make('title')
                    ->label('标题')
                    ->placeholder('如：「城建装饰」整装团购')
                    ->required(),
                Select::make('status')
                    ->label('状态')
                    ->options(ProjectStatus::class)
                    ->default(ProjectStatus::Seeking)
                    ->required(),
                Toggle::make('is_approved')
                    ->label('审核通过（对业主展示）')
                    ->helperText('业主发起的团购默认待审核，打开后才会出现在小程序列表里'),
                TextInput::make('target_households')
                    ->label('目标户数')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('pitch')
                    ->label('团长的话')
                    ->rows(4)
                    ->placeholder('自家已签约、条件对每户一样、权益公开……写给邻居看的话')
                    ->columnSpanFull(),
                TextInput::make('perk')
                    ->label('阶梯优惠')
                    ->placeholder('如：满 20 户赠全屋水电升级')
                    ->default(''),
                Repeater::make('terms')
                    ->label('团购条件')
                    ->schema([
                        TextInput::make('label')->label('名目')->required(),
                        TextInput::make('value')->label('内容')->required(),
                    ])
                    ->columns(2)
                    ->default([])
                    ->columnSpanFull(),
                Repeater::make('glossary')
                    ->label('买前必懂（3~5 条，大白话）')
                    ->schema([
                        TextInput::make('term')->label('术语')->required(),
                        Textarea::make('explain')->label('大白话解释 + 对决策的影响')->rows(2)->required(),
                    ])
                    ->default([])
                    ->columnSpanFull(),
            ]);
    }
}
