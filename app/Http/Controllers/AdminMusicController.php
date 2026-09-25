<?php
namespace App\Http\Controllers;

use App\Models\MusicTrack;
use App\Services\StorageQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminMusicController extends Controller
{
    public function index()
    {
        return view('admin.music',[
            'tracks'=>MusicTrack::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function edit(MusicTrack $track)
    {
        return view('admin.music',[
            'tracks'=>MusicTrack::orderBy('sort_order')->orderBy('id')->get(),
            'editTrack'=>$track,
        ]);
    }

    public function save(Request $request, ?MusicTrack $track=null)
    {
        $data=$request->validate([
            'title'=>'required|string|max:255',
            'artist'=>'nullable|string|max:255',
            'audio_file'=>[$track ? 'nullable' : 'required','file','max:51200','mimes:mp3,wav,ogg,m4a,aac'],
            'sort_order'=>'nullable|integer|min:0|max:999999',
            'is_active'=>'nullable|boolean',
        ],[
            'audio_file.required'=>'Выберите аудиофайл.',
            'audio_file.max'=>'Максимальный размер аудиофайла — 50 МБ.',
            'audio_file.mimes'=>'Разрешены MP3, WAV, OGG, M4A и AAC.',
        ]);

        $track ??= new MusicTrack();

        if($request->hasFile('audio_file')){
            $file=$request->file('audio_file');

            if(!StorageQuota::canStore((int)$file->getSize())){
                return back()->withErrors(['audio_file'=>'Недостаточно места в хранилище.'])->withInput();
            }

            if($track->file_path){
                Storage::disk('public')->delete($track->file_path);
            }

            $data['file_path']=$file->store('music','public');
            $data['file_name']=$file->getClientOriginalName();
            $data['mime_type']=$file->getMimeType();
            $data['file_size']=$file->getSize();
        }

        unset($data['audio_file']);
        $data['sort_order']=$data['sort_order'] ?? 0;
        $data['is_active']=$request->boolean('is_active');

        $track->fill($data)->save();

        return redirect()->route('admin.music')->with('success','Музыкальный трек сохранён.');
    }

    public function delete(MusicTrack $track)
    {
        if($track->file_path){
            Storage::disk('public')->delete($track->file_path);
        }
        $track->delete();

        return back()->with('success','Трек удалён.');
    }
}
