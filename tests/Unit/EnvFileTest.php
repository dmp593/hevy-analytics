<?php

namespace Tests\Unit;

use App\Support\EnvFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * .env is a hand-maintained file holding the database password and whatever
 * comments the operator wrote themselves. A writer that mangles it is a writer
 * that eventually costs someone an afternoon, so these pin the edits.
 */
class EnvFileTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/env-test-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::delete($this->path);

        parent::tearDown();
    }

    private function write(string $contents): EnvFile
    {
        File::put($this->path, $contents);

        return new EnvFile($this->path);
    }

    public function test_it_updates_a_key_in_place(): void
    {
        $env = $this->write("APP_NAME=Test\nPADDLE_SELLER_ID=\nDB_CONNECTION=pgsql\n");

        $env->set(['PADDLE_SELLER_ID' => '123456']);

        $contents = File::get($this->path);

        $this->assertStringContainsString('PADDLE_SELLER_ID=123456', $contents);
        $this->assertSame(1, substr_count($contents, 'PADDLE_SELLER_ID'));
        // Order is preserved, so the file still reads the way it was written.
        $this->assertLessThan(strpos($contents, 'DB_CONNECTION'), strpos($contents, 'PADDLE_SELLER_ID'));
    }

    public function test_it_leaves_everything_else_untouched(): void
    {
        $original = "APP_NAME=\"Hevy Analytics\"\n# a comment I wrote\nDB_PASSWORD=hunter2\n\nMAIL_MAILER=log\n";
        $env = $this->write($original);

        $env->set(['PADDLE_PRICE_ID' => 'pri_01abc']);

        $contents = File::get($this->path);

        foreach (['APP_NAME="Hevy Analytics"', '# a comment I wrote', 'DB_PASSWORD=hunter2', 'MAIL_MAILER=log'] as $line) {
            $this->assertStringContainsString($line, $contents);
        }
    }

    public function test_it_appends_keys_that_are_not_present(): void
    {
        $env = $this->write("APP_NAME=Test\n");

        $env->set(['PADDLE_PRICE_ID' => 'pri_01abc'], 'Added by the wizard');

        $contents = File::get($this->path);

        $this->assertStringContainsString('# Added by the wizard', $contents);
        $this->assertStringContainsString('PADDLE_PRICE_ID=pri_01abc', $contents);
    }

    public function test_running_twice_replaces_rather_than_duplicates(): void
    {
        $env = $this->write("APP_NAME=Test\n");

        $env->set(['PADDLE_SELLER_ID' => '111']);
        $env->set(['PADDLE_SELLER_ID' => '222']);

        $contents = File::get($this->path);

        $this->assertSame(1, substr_count($contents, 'PADDLE_SELLER_ID'));
        $this->assertStringContainsString('PADDLE_SELLER_ID=222', $contents);
    }

    /**
     * dotenv stops a bare value at the first space and treats a leading # as a
     * comment, so an unquoted "€8" or "Hevy Analytics" arrives truncated with no
     * error anywhere.
     */
    public function test_values_needing_quotes_get_them(): void
    {
        $this->assertSame('"€8"', EnvFile::quote('€8'));
        $this->assertSame('"Hevy Analytics"', EnvFile::quote('Hevy Analytics'));
        $this->assertSame('"a # hash"', EnvFile::quote('a # hash'));

        // Bare tokens are left alone, so the file does not fill up with quotes.
        $this->assertSame('pri_01abc', EnvFile::quote('pri_01abc'));
        $this->assertSame('true', EnvFile::quote('true'));
        $this->assertSame('https://example.com/v1', EnvFile::quote('https://example.com/v1'));
    }

    public function test_an_embedded_quote_is_escaped(): void
    {
        $this->assertSame('"say \\"hi\\""', EnvFile::quote('say "hi"'));
    }

    /** A key must not match a longer one that merely starts the same way. */
    public function test_a_similarly_named_key_is_not_overwritten(): void
    {
        $env = $this->write("PADDLE_API_KEY=old\nPADDLE_API_KEY_BACKUP=keepme\n");

        $env->set(['PADDLE_API_KEY' => 'new']);

        $contents = File::get($this->path);

        $this->assertStringContainsString('PADDLE_API_KEY=new', $contents);
        $this->assertStringContainsString('PADDLE_API_KEY_BACKUP=keepme', $contents);
    }

    /** A commented-out setting is a note to self, not a value to rewrite. */
    public function test_a_commented_out_key_is_left_commented(): void
    {
        $env = $this->write("# PADDLE_SELLER_ID=999\nAPP_NAME=Test\n");

        $env->set(['PADDLE_SELLER_ID' => '123']);

        $contents = File::get($this->path);

        $this->assertStringContainsString('# PADDLE_SELLER_ID=999', $contents);
        $this->assertStringContainsString("\nPADDLE_SELLER_ID=123", $contents);
    }

    public function test_it_does_not_run_lines_together_when_appending(): void
    {
        $env = $this->write('APP_NAME=Test');   // no trailing newline

        $env->set(['PADDLE_PRICE_ID' => 'pri_01abc']);

        $this->assertStringNotContainsString('APP_NAME=TestPADDLE', File::get($this->path));
    }
}
