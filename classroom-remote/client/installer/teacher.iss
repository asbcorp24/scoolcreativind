#define MyAppName "Classroom Teacher"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "SHKI"
#define MyAppExeName "ClassroomTeacher.exe"

[Setup]
AppId={{6B487B18-7C94-4FD3-96A3-1C7EA85ABDA1}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName={autopf}\SHKI\Classroom Teacher
DefaultGroupName=SHKI Classroom
DisableProgramGroupPage=yes
PrivilegesRequired=lowest
OutputDir=..\dist\installers
OutputBaseFilename=ClassroomTeacher-Setup
SetupIconFile=..\assets\teacher.ico
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
Source: "..\dist\teacher\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{group}\Classroom Teacher"; Filename: "{app}\{#MyAppExeName}"
Name: "{autodesktop}\Classroom Teacher"; Filename: "{app}\{#MyAppExeName}"; Tasks: desktopicon

[Run]
Filename: "{app}\{#MyAppExeName}"; Description: "Запустить Classroom Teacher"; Flags: nowait postinstall skipifsilent
