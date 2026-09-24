<?php

namespace Database\Seeders;

use App\Models\Competition;
use Illuminate\Database\Seeder;

class CompetitionSeeder extends Seeder
{
    public function run(): void
    {
        $competitions = [
            [
                'title'=>'Digital Art Challenge 2026',
                'organizer'=>'Школа креативных индустрий · Волжск',
                'starts_on'=>'2026-10-01',
                'ends_on'=>'2026-10-31',
                'location'=>'Онлайн',
                'description'=>'Конкурс цифрового искусства для учеников школы. Можно представить постер, иллюстрацию, фирменный стиль, цифровой коллаж или экспериментальную графику.',
                'url'=>null,
                'cover'=>null,
                'is_published'=>true,
            ],
            [
                'title'=>'3D Future Worlds',
                'organizer'=>'Студия анимации и 3D-графики',
                'starts_on'=>'2026-10-10',
                'ends_on'=>'2026-11-15',
                'location'=>'ШКИ, Волжск',
                'description'=>'Создайте авторскую 3D-сцену будущего: город, транспорт, персонажа, архитектурный объект или фантастический мир. Можно приложить GLB/GLTF, рендеры и видео.',
                'url'=>null,
                'cover'=>null,
                'is_published'=>true,
            ],
            [
                'title'=>'VR / AR Experience',
                'organizer'=>'Студия интерактивных цифровых технологий',
                'starts_on'=>'2026-11-01',
                'ends_on'=>'2026-12-05',
                'location'=>'Онлайн + очный финал',
                'description'=>'Конкурс интерактивных проектов, VR-сцен и AR-прототипов. Оцениваются идея, интерактивность, визуальная подача и качество пользовательского опыта.',
                'url'=>null,
                'cover'=>null,
                'is_published'=>true,
            ],
            [
                'title'=>'Sound Design Battle',
                'organizer'=>'Студия звукорежиссуры',
                'starts_on'=>'2026-10-15',
                'ends_on'=>'2026-11-20',
                'location'=>'ШКИ, Волжск',
                'description'=>'Создайте звуковую атмосферу, саунд-дизайн для короткой сцены или авторскую композицию. Можно отправить MP3/WAV, ссылку на проект и описание идеи.',
                'url'=>null,
                'cover'=>null,
                'is_published'=>true,
            ],
            [
                'title'=>'Short Film / Reels',
                'organizer'=>'Студия фото- и видеопроизводства',
                'starts_on'=>'2026-11-10',
                'ends_on'=>'2026-12-20',
                'location'=>'Онлайн',
                'description'=>'Короткий фильм, клип, репортаж или вертикальное видео до 3 минут. Важны история, монтаж, работа с камерой и визуальная выразительность.',
                'url'=>null,
                'cover'=>null,
                'is_published'=>true,
            ],
            [
                'title'=>'Creative Identity',
                'organizer'=>'Студия дизайна',
                'starts_on'=>'2027-01-15',
                'ends_on'=>'2027-02-28',
                'location'=>'ШКИ, Волжск',
                'description'=>'Разработайте айдентику вымышленного бренда: логотип, цветовую палитру, типографику, ключевые носители и презентацию концепции.',
                'url'=>null,
                'cover'=>null,
                'is_published'=>true,
            ],
        ];

        foreach ($competitions as $competition) {
            Competition::updateOrCreate(
                ['title'=>$competition['title']],
                $competition
            );
        }
    }
}
