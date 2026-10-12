@echo off
setlocal
cd /d "%~dp0"

if "%QT_ROOT%"=="" (
  echo Set QT_ROOT, for example:
  echo   set QT_ROOT=C:\Qt\6.8.3\msvc2022_64
  exit /b 1
)

if "%VCPKG_ROOT%"=="" (
  echo Set VCPKG_ROOT to your vcpkg directory, for example:
  echo   set VCPKG_ROOT=C:\vcpkg
  echo.
  echo If vcpkg is not installed:
  echo   git clone https://github.com/microsoft/vcpkg C:\vcpkg
  echo   C:\vcpkg\bootstrap-vcpkg.bat
  exit /b 1
)

if not exist "%VCPKG_ROOT%\scripts\buildsystems\vcpkg.cmake" (
  echo vcpkg toolchain not found under VCPKG_ROOT=%VCPKG_ROOT%
  exit /b 1
)

if not exist build mkdir build

set "CMAKE_VS_GENERATOR=Visual Studio 17 2022"
set "VSWHERE=%ProgramFiles(x86)%\Microsoft Visual Studio\Installer\vswhere.exe"
if exist "%VSWHERE%" (
  for /f "usebackq tokens=*" %%V in (`"%VSWHERE%" -latest -property installationVersion`) do set "VS_VERSION=%%V"
)
if defined VS_VERSION (
  if "%VS_VERSION:~0,2%"=="18" set "CMAKE_VS_GENERATOR=Visual Studio 18 2026"
)

echo Using CMake generator: %CMAKE_VS_GENERATOR%

cmake -S . -B build ^
  -G "%CMAKE_VS_GENERATOR%" -A x64 ^
  -DCMAKE_PREFIX_PATH="%QT_ROOT%" ^
  -DCMAKE_TOOLCHAIN_FILE="%VCPKG_ROOT%\scripts\buildsystems\vcpkg.cmake" ^
  -DVCPKG_TARGET_TRIPLET=x64-windows

if errorlevel 1 exit /b 1

cmake --build build --config Release
if errorlevel 1 exit /b 1

if exist "%QT_ROOT%\bin\windeployqt.exe" (
  "%QT_ROOT%\bin\windeployqt.exe" --release build\Release\ClassroomTeacher.exe
  "%QT_ROOT%\bin\windeployqt.exe" --release build\Release\ClassroomStudent.exe
)

echo.
echo READY:
echo   build\Release\ClassroomTeacher.exe
echo   build\Release\ClassroomStudent.exe
endlocal
