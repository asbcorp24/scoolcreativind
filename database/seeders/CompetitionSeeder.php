<?php

namespace Database\Seeders;

use App\Models\Competition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class CompetitionSeeder extends Seeder
{
    public function run(): void
    {
        $supportsDocuments = Schema::hasColumn('competitions', 'required_documents_json');

        $standardDocuments = [
            ['key'=>'doc_1','label'=>'Заявка участника'],
            ['key'=>'doc_2','label'=>'Согласие на обработку персональных данных'],
            ['key'=>'doc_3','label'=>'Согласие родителя / законного представителя'],
        ];

        $competitions = [
            [
                'title'=>'Digital Art Challenge 2026',
                'organizer'=>'Школа креативных индустрий · Волжск',
                'starts_on'=>'2026-10-01',
                'ends_on'=>'2026-10-31',
                'location'=>'Онлайн',
                'description'=>'Конкурс цифрового искусства: постеры, иллюстрации, айдентика, цифровые коллажи и экспериментальная графика.',
                'documents'=>$standardDocuments,
            ],
            [
                'title'=>'3D Future Worlds',
                'organizer'=>'Студия анимации и 3D-графики',
                'starts_on'=>'2026-10-10',
                'ends_on'=>'2026-11-15',
                'location'=>'ШКИ, Волжск',
                'description'=>'Создание авторской 3D-сцены будущего: город, транспорт, персонаж, архитектурный объект или фантастический мир.',
                'documents'=>[
                    ...$standardDocuments,
                    ['key'=>'doc_4','label'=>'Краткое описание 3D-проекта'],
                ],
            ],
            [
                'title'=>'VR / AR Experience',
                'organizer'=>'Студия интерактивных цифровых технологий',
                'starts_on'=>'2026-11-01',
                'ends_on'=>'2026-12-05',
                'location'=>'Онлайн + очный финал',
                'description'=>'Конкурс интерактивных проектов, VR-сцен и AR-прототипов. Оцениваются идея, интерактивность и пользовательский опыт.',
                'documents'=>[
                    ...$standardDocuments,
                    ['key'=>'doc_4','label'=>'Описание проекта'],
                    ['key'=>'doc_5','label'=>'Ссылка на демонстрацию / видео проекта'],
                ],
            ],
            [
                'title'=>'Sound Design Battle',
                'organizer'=>'Студия звукорежиссуры',
                'starts_on'=>'2026-10-15',
                'ends_on'=>'2026-11-20',
                'location'=>'ШКИ, Волжск',
                'description'=>'Конкурс саунд-дизайна, звуковых атмосфер и авторских композиций.',
                'documents'=>[
                    ...$standardDocuments,
                    ['key'=>'doc_4','label'=>'Описание музыкальной / звуковой работы'],
                ],
            ],
            [
                'title'=>'Short Film / Reels',
                'organizer'=>'Студия фото- и видеопроизводства',
                'starts_on'=>'2026-11-10',
                'ends_on'=>'2026-12-20',
                'location'=>'Онлайн',
                'description'=>'Короткий фильм, клип, репортаж или вертикальное видео до 3 минут.',
                'documents'=>[
                    ...$standardDocuments,
                    ['key'=>'doc_4','label'=>'Согласие на публикацию фото и видео'],
                    ['key'=>'doc_5','label'=>'Краткое описание видеоработы'],
                ],
            ],
            [
                'title'=>'Creative Identity',
                'organizer'=>'Студия дизайна',
                'starts_on'=>'2027-01-15',
                'ends_on'=>'2027-02-28',
                'location'=>'ШКИ, Волжск',
                'description'=>'Конкурс айдентики: логотип, цветовая палитра, типографика и ключевые носители бренда.',
                'documents'=>[
                    ...$standardDocuments,
                    ['key'=>'doc_4','label'=>'Описание концепции фирменного стиля'],
                ],
            ],
        ];

        foreach ($competitions as $item) {
            $data = [
                'organizer'=>$item['organizer'],
                'starts_on'=>$item['starts_on'],
                'ends_on'=>$item['ends_on'],
                'location'=>$item['location'],
                'description'=>$item['description'],
                'url'=>null,
                'cover'=>null,
                'is_published'=>true,
            ];

            if ($supportsDocuments) {
                $data['required_documents_json']=$item['documents'];
            }

            Competition::updateOrCreate(
                ['title'=>$item['title']],
                $data
            );
        }
    }
}
