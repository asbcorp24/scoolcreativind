@echo off
setlocal
cd /d "%~dp0"

if "%QT_ROOT%"=="" (
  echo Set QT_ROOT, for example:
  echo   set QT_ROOT=C:\Qt\6.8.3\msvc2022_64
  exit /b 1
)

if not exist build mkdir build
cmake -S . -B build -G "Visual Studio 17 2022" -A x64 -DCMAKE_PREFIX_PATH="%QT_ROOT%"
if errorlevel 1 exit /b 1

cmake --build build --config Release
if errorlevel 1 exit /b 1

if exist "%QT_ROOT%\bin\windeployqt.exe" (
  "%QT_ROOT%\bin\windeployqt.exe" build\Release\ClassroomTeacher.exe
  "%QT_ROOT%\bin\windeployqt.exe" build\Release\ClassroomStudent.exe
)

echo.
echo READY:
echo   build\Release\ClassroomTeacher.exe
echo   build\Release\ClassroomStudent.exe
endlocal
