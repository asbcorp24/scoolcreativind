<?php
namespace Database\Seeders;

use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $studios = [
            ['title'=>'Студия анимации и 3D-графики','slug'=>'animation-3d','subtitle'=>'Мультфильмы, персонажи, моделирование и цифровые миры','icon'=>'◈','accent'=>'#8a5cff','sort_order'=>1,'description'=>"Создание мультфильмов, трёхмерных моделей и персонажей. Работа с композицией, движением, сценой, светом, материалами и современным 3D-софтом."],
            ['title'=>'Студия дизайна','slug'=>'design','subtitle'=>'Графический стиль, брендинг и визуальные системы','icon'=>'✦','accent'=>'#ff4fd8','sort_order'=>2,'description'=>"Основы графического дизайна, типографика, брендинг, айдентика, плакат, цифровые интерфейсы и создание цельных визуальных систем."],
            ['title'=>'Студия звукорежиссуры','slug'=>'sound','subtitle'=>'Запись, монтаж, сведение и работа со звуком','icon'=>'◉','accent'=>'#00e5ff','sort_order'=>3,'description'=>"Запись вокала и инструментов, основы акустики, работа с микрофонами, обработка, монтаж, сведение и подготовка готового аудиоматериала."],
            ['title'=>'Студия электронной музыки','slug'=>'electronic-music','subtitle'=>'Треки, синтез, ритм и саунд-дизайн','icon'=>'⌁','accent'=>'#b8ff5c','sort_order'=>4,'description'=>"Создание электронной музыки, работа с DAW, синтезаторами, семплами и эффектами. От идеи и ритма до собственного законченного трека."],
            ['title'=>'Студия фото- и видеопроизводства','slug'=>'photo-video','subtitle'=>'Съёмка, свет, монтаж, интервью и клипы','icon'=>'◎','accent'=>'#ff9d4d','sort_order'=>5,'description'=>"Съёмка клипов, интервью и репортажей, работа со светом, камерой и композицией, видеомонтаж, цветокоррекция и подготовка контента."],
            ['title'=>'Студия интерактивных цифровых технологий','slug'=>'interactive-vr-ar','subtitle'=>'VR, AR, интерактив и прототипирование','icon'=>'⬡','accent'=>'#5c7cff','sort_order'=>6,'description'=>"Погружение в VR и AR, интерактивные сцены, основы разработки цифровых продуктов и прототипирование новых способов взаимодействия с контентом."],
        ];
        foreach($studios as $studio) Studio::updateOrCreate(['slug'=>$studio['slug']],array_merge($studio,['is_active'=>true]));

        $email = env('ADMIN_EMAIL','admin@school.local');
        User::updateOrCreate(['email'=>$email],[
            'name'=>'Администратор',
            'phone'=>null,
            'password'=>Hash::make(env('ADMIN_PASSWORD','ChangeMe123!')),
            'is_admin'=>true
        ]);
        $this->call(QuizSeeder::class);
        $this->call(CompetitionSeeder::class);
    }
}
