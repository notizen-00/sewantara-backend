<?php

namespace App\Support;

use YasinTgh\LaravelPostman\Collections\Builder;

class PostmanCollectionBuilder extends Builder
{
    public function __construct(
        private readonly Builder $builder,
    ) {}

    public function build(array $routes): array
    {
        $collection = $this->replaceTenantPathParameters(
            $this->builder->build($routes),
        );

        $collection['event'] = $this->events();
        $collection['variable'] = [
            ...$collection['variable'],
            [
                'key' => 'tenant',
                'value' => 'your-tenant-id',
                'type' => 'string',
                'description' => 'ID tenant aktif. Otomatis diperbarui setelah login tenant berhasil.',
            ],
            [
                'key' => 'x_branch_id',
                'value' => '1',
                'type' => 'string',
                'description' => 'ID cabang aktif yang otomatis dikirim sebagai header X-Branch-Id.',
            ],
        ];

        return $collection;
    }

    private function replaceTenantPathParameters(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(':tenant', '{{tenant}}', $value);
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->replaceTenantPathParameters($item);
        }

        return $value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function events(): array
    {
        return [
            [
                'listen' => 'prerequest',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => [
                        'const requestUrl = pm.variables.replaceIn(pm.request.url.toString());',
                        "const isTenantRequest = requestUrl.includes('/api/tenant/');",
                        "const isTenantLogin = requestUrl.includes('/api/tenant/auth/login');",
                        '',
                        'if (isTenantRequest && !isTenantLogin) {',
                        '    pm.request.headers.upsert({',
                        "        key: 'X-Branch-Id',",
                        "        value: pm.variables.replaceIn('{{x_branch_id}}'),",
                        '    });',
                        '}',
                    ],
                ],
            ],
            [
                'listen' => 'test',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => [
                        'const requestUrl = pm.variables.replaceIn(pm.request.url.toString());',
                        '',
                        "if (requestUrl.includes('/api/tenant/auth/login') && pm.response.code === 200) {",
                        '    const response = pm.response.json();',
                        '',
                        "    pm.collectionVariables.set('auth_token', response.data.access_token);",
                        "    pm.collectionVariables.set('tenant', response.data.user.tenant_id);",
                        '}',
                    ],
                ],
            ],
        ];
    }
}
