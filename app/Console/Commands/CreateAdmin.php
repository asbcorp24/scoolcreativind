<?php
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature='admin:create {email} {password} {--name=Администратор}';
    protected $description='Create or update an administrator account';

    public function handle(): int
    {
        $email=(string)$this->argument('email');
        $password=(string)$this->argument('password');

        if (mb_strlen($password)<8) {
            $this->error('Пароль должен содержать не менее 8 символов.');
            return self::FAILURE;
        }

        $user=User::updateOrCreate(
            ['email'=>$email],
            [
                'name'=>(string)$this->option('name'),
                'password'=>Hash::make($password),
                'is_admin'=>true,
            ]
        );

        $this->info('Администратор создан/обновлён: '.$user->email);
        $this->info('Вход: '.url('/admin/login'));

        return self::SUCCESS;
    }
}
