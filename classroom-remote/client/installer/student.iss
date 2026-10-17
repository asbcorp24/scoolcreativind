#define MyAppName "Classroom Student"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "SHKI"
#define MyAppExeName "ClassroomStudent.exe"

[Setup]
AppId={{2AA75D7F-062E-4515-9C1F-EA4B4586B461}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName={autopf}\SHKI\Classroom Student
DefaultGroupName=SHKI Classroom
DisableProgramGroupPage=yes
PrivilegesRequired=lowest
OutputDir=..\dist\installers
OutputBaseFilename=ClassroomStudent-Setup
SetupIconFile=..\assets\student.ico
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
UninstallDisplayIcon={app}\{#MyAppExeName}
CloseApplications=yes
RestartApplications=no

[Languages]
Name: "russian"; MessagesFile: "compiler:Languages\Russian.isl"

[Tasks]
Name: "desktopicon"; Description: "Создать ярлык на рабочем столе"; GroupDescription: "Дополнительные значки:"; Flags: unchecked

[Files]
Source: "..\dist\student\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{group}\Classroom Student"; Filename: "{app}\{#MyAppExeName}"
Name: "{autodesktop}\Classroom Student"; Filename: "{app}\{#MyAppExeName}"; Tasks: desktopicon

[Run]
Filename: "{app}\{#MyAppExeName}"; Description: "Запустить Classroom Student"; Flags: nowait postinstall skipifsilent
