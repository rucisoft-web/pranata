<?php

if (! function_exists('workspace_route')) {
    function workspace_route(string $name, mixed $parameters = []): string
    {
        $demoNames = ['dashboard', 'risks.index', 'risks.show', 'obligations.index', 'obligations.show', 'compliance.index', 'compliance.show', 'audits.index', 'audits.show', 'findings.index', 'findings.show', 'evidence.download'];
        if (request()->attributes->get('demo_mode') && in_array($name, $demoNames, true)) { $name = 'demo.'.$name; }

        return route($name, $parameters);
    }
}
