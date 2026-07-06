<?php

// Orchestra Testbench's base_path() resolves to a shared skeleton directory
// under vendor/, reused across the whole suite — clean AGENTS.md/CLAUDE.md
// before and after every test so one test's writes can't bleed into another.
function cleanAgentDocs(): void
{
    @unlink(base_path('AGENTS.md'));
    @unlink(base_path('CLAUDE.md'));
}

beforeEach(fn () => cleanAgentDocs());
afterEach(fn () => cleanAgentDocs());

test('install creates AGENTS.md with the core section and CLAUDE.md importing it', function () {
    $this->artisan('jess:install', ['--no-migrate' => true])->assertExitCode(0);

    $agents = file_get_contents(base_path('AGENTS.md'));
    expect($agents)->toContain('<!-- jess:core:start -->');
    expect($agents)->toContain('Jess::fragment');
    expect($agents)->toContain('<!-- jess:core:end -->');

    $claude = file_get_contents(base_path('CLAUDE.md'));
    expect($claude)->toContain('@AGENTS.md');
});

test('install appends to an existing AGENTS.md without clobbering host content', function () {
    file_put_contents(base_path('AGENTS.md'), "# Host app\n\nSome existing agent instructions.\n");

    $this->artisan('jess:install', ['--no-migrate' => true])->assertExitCode(0);

    $agents = file_get_contents(base_path('AGENTS.md'));
    expect($agents)->toContain('Some existing agent instructions.');
    expect($agents)->toContain('<!-- jess:core:start -->');
});

test('install prepends the import to an existing CLAUDE.md without clobbering it', function () {
    file_put_contents(base_path('CLAUDE.md'), "# Host app\n\nHost-specific Claude instructions.\n");

    $this->artisan('jess:install', ['--no-migrate' => true])->assertExitCode(0);

    $claude = file_get_contents(base_path('CLAUDE.md'));
    expect($claude)->toContain('@AGENTS.md');
    expect($claude)->toContain('Host-specific Claude instructions.');
});

test('install is idempotent — running twice does not duplicate either file', function () {
    $this->artisan('jess:install', ['--no-migrate' => true])->assertExitCode(0);
    $this->artisan('jess:install', ['--no-migrate' => true])->assertExitCode(0);

    $agents = file_get_contents(base_path('AGENTS.md'));
    expect(substr_count($agents, '<!-- jess:core:start -->'))->toBe(1);

    $claude = file_get_contents(base_path('CLAUDE.md'));
    expect(substr_count($claude, '@AGENTS.md'))->toBe(1);
});

test('--no-docs skips AGENTS.md and CLAUDE.md entirely', function () {
    $this->artisan('jess:install', ['--no-migrate' => true, '--no-docs' => true])->assertExitCode(0);

    expect(is_file(base_path('AGENTS.md')))->toBeFalse();
    expect(is_file(base_path('CLAUDE.md')))->toBeFalse();
});
