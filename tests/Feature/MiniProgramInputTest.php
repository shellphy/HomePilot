<?php

use Symfony\Component\Process\Process;

test('input values sync to page data without rerendering the native control', function () {
    $script = <<<'JS'
const { syncInputValue } = require('./miniprogram/utils/input');

let renderCount = 0;
const context = {
  data: {
    description: '前面的内容中间要删除后面的内容',
    terms: [{ value: '原来的团购条件' }],
  },
  setData() {
    renderCount += 1;
  },
};

syncInputValue(context, 'description', '前面的内容后面的内容');
syncInputValue(context, 'terms[0].value', '修改后的团购条件');

process.stdout.write(JSON.stringify({ data: context.data, renderCount }));
JS;

    $process = new Process(['node', '-e', $script], base_path());
    $process->mustRun();

    expect(json_decode($process->getOutput(), true))
        ->toBe([
            'data' => [
                'description' => '前面的内容后面的内容',
                'terms' => [['value' => '修改后的团购条件']],
            ],
            'renderCount' => 0,
        ]);
});
