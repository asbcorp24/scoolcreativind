@echo off
setlocal EnableExtensions EnableDelayedExpansion
title SHKI Classroom Remote - Windows setup and build
cd /d "%~dp0"

rem ============================================================
rem  SHKI Classroom Remote - automatic Windows setup + build
rem  Builds with MSVC x64. MSYS2 may be installed, but is not
rem  used as the compiler toolchain for this Qt/MSVC project.
rem ============================================================

set "QT_VERSION=6.8.3"
set "QT_ARCH=win64_msvc2022_64"
set "QT_BASE=C:\Qt"
set "QT_ROOT=%QT_BASE%\%QT_VERSION%\msvc2022_64"
set "VCPKG_ROOT=C:\vcpkg"
set "VCPKG_DEFAULT_TRIPLET=x64-windows"
set "VCPKG_TARGET_TRIPLET=x64-windows"

echo.
echo ============================================================
echo   SHKI Classroom Remote - automatic Windows builder
echo ============================================================
echo.
echo Project: %CD%
echo Qt:      %QT_ROOT%
echo vcpkg:   %VCPKG_ROOT%
echo.

rem ---------- Admin check ----------
net session >nul 2>&1
if errorlevel 1 (
    echo [INFO] Administrator rights are required for first-time installs.
    echo [INFO] Restarting this script as Administrator...
    powershell -NoProfile -ExecutionPolicy Bypass -Command ^
      "Start-Process -FilePath '%~f0' -Verb RunAs -WorkingDirectory '%CD%'"
    exit /b
)

rem ---------- winget ----------
where winget >nul 2>&1
if errorlevel 1 (
    echo.
    echo [ERROR] winget was not found.
    echo Install "App Installer" from Microsoft Store, then run this BAT again.
    echo.
    pause
    exit /b 1
)

rem ---------- Git ----------
where git >nul 2>&1
if errorlevel 1 (
    if exist "C:\Program Files\Git\cmd\git.exe" (
        set "PATH=C:\Program Files\Git\cmd;%PATH%"
    )
)

where git >nul 2>&1
if errorlevel 1 (
    echo [INSTALL] Git...
    winget install --id Git.Git -e --silent --accept-package-agreements --accept-source-agreements
    if exist "C:\Program Files\Git\cmd\git.exe" set "PATH=C:\Program Files\Git\cmd;%PATH%"
)

where git >nul 2>&1
if errorlevel 1 (
    if exist "C:\msys64\usr\bin\git.exe" (
        echo [INFO] Using Git from MSYS2 as fallback.
        set "PATH=C:\msys64\usr\bin;%PATH%"
    )
)

where git >nul 2>&1
if errorlevel 1 goto :git_error
echo [OK] Git found.

rem ---------- CMake ----------
where cmake >nul 2>&1
if errorlevel 1 (
    if exist "C:\Program Files\CMake\bin\cmake.exe" (
        set "PATH=C:\Program Files\CMake\bin;%PATH%"
    )
)

where cmake >nul 2>&1
if errorlevel 1 (
    echo [INSTALL] CMake...
    winget install --id Kitware.CMake -e --silent --accept-package-agreements --accept-source-agreements
    if exist "C:\Program Files\CMake\bin\cmake.exe" set "PATH=C:\Program Files\CMake\bin;%PATH%"
)

where cmake >nul 2>&1
if errorlevel 1 goto :cmake_error
echo [OK] CMake found.

rem ---------- Go (for embedded LAN server) ----------
where go >nul 2>&1
if errorlevel 1 (
    if exist "C:\Program Files\Go\bin\go.exe" set "PATH=C:\Program Files\Go\bin;%PATH%"
)
where go >nul 2>&1
if errorlevel 1 (
    echo [INSTALL] Go...
    winget install --id GoLang.Go -e --silent --accept-package-agreements --accept-source-agreements
    if exist "C:\Program Files\Go\bin\go.exe" set "PATH=C:\Program Files\Go\bin;%PATH%"
)
where go >nul 2>&1
if errorlevel 1 goto :go_error
echo [OK] Go found.

rem ---------- Visual Studio Build Tools / MSVC ----------
set "VSWHERE=%ProgramFiles(x86)%\Microsoft Visual Studio\Installer\vswhere.exe"
set "VS_INSTALL="
if exist "%VSWHERE%" (
    for /f "usebackq tokens=*" %%I in (`"%VSWHERE%" -latest -products * -requires Microsoft.VisualStudio.Component.VC.Tools.x86.x64 -property installationPath`) do set "VS_INSTALL=%%I"
)

if not defined VS_INSTALL (
    echo [INSTALL] Visual Studio 2022 Build Tools + C++ workload...
    echo          This is the largest download and can take a while.
    winget install --id Microsoft.VisualStudio.2022.BuildTools -e ^
      --silent --accept-package-agreements --accept-source-agreements ^
      --override "--wait --passive --norestart --add Microsoft.VisualStudio.Workload.VCTools --includeRecommended"

    if exist "%VSWHERE%" (
        for /f "usebackq tokens=*" %%I in (`"%VSWHERE%" -latest -products * -requires Microsoft.VisualStudio.Component.VC.Tools.x86.x64 -property installationPath`) do set "VS_INSTALL=%%I"
    )
)

if not defined VS_INSTALL goto :vs_error
echo [OK] MSVC found: %VS_INSTALL%

rem ---------- Python ----------
set "PY_CMD="
where py >nul 2>&1
if not errorlevel 1 set "PY_CMD=py -3"

if not defined PY_CMD (
    where python >nul 2>&1
    if not errorlevel 1 set "PY_CMD=python"
)

if not defined PY_CMD (
    echo [INSTALL] Python 3.12...
    winget install --id Python.Python.3.12 -e --silent --accept-package-agreements --accept-source-agreements

    if exist "%LocalAppData%\Programs\Python\Python312\python.exe" (
        set "PY_CMD=\"%LocalAppData%\Programs\Python\Python312\python.exe\""
    ) else if exist "C:\Program Files\Python312\python.exe" (
        set "PY_CMD=\"C:\Program Files\Python312\python.exe\""
    ) else (
        where py >nul 2>&1
        if not errorlevel 1 set "PY_CMD=py -3"
    )
)

if not defined PY_CMD goto :python_error
echo [OK] Python found.

rem ---------- Qt ----------
if not exist "%QT_ROOT%\bin\qmake.exe" (
    echo [INSTALL] aqtinstall...
    call %PY_CMD% -m pip install --upgrade pip aqtinstall
    if errorlevel 1 goto :aqt_error

    echo [INSTALL] Qt %QT_VERSION% MSVC 2022 x64 + Multimedia + WebSockets...
    if not exist "%QT_BASE%" mkdir "%QT_BASE%"
    call %PY_CMD% -m aqt install-qt windows desktop %QT_VERSION% %QT_ARCH% ^
      -m qtmultimedia qtwebsockets --outputdir "%QT_BASE%"
    if errorlevel 1 goto :qt_error
)

if not exist "%QT_ROOT%\bin\qmake.exe" goto :qt_error
echo [OK] Qt found: %QT_ROOT%

rem ---------- vcpkg ----------
if not exist "%VCPKG_ROOT%\vcpkg.exe" (
    if exist "%VCPKG_ROOT%" (
        echo [INFO] Existing incomplete %VCPKG_ROOT% detected.
    ) else (
        echo [INSTALL] Cloning vcpkg...
        git clone https://github.com/microsoft/vcpkg.git "%VCPKG_ROOT%"
        if errorlevel 1 goto :vcpkg_error
    )

    echo [INSTALL] Bootstrapping vcpkg...
    call "%VCPKG_ROOT%\bootstrap-vcpkg.bat" -disableMetrics
    if errorlevel 1 goto :vcpkg_error
)

if not exist "%VCPKG_ROOT%\vcpkg.exe" goto :vcpkg_error
echo [OK] vcpkg found.

rem ---------- dependencies ----------
echo.
echo [INSTALL] Project native dependencies via vcpkg...
"%VCPKG_ROOT%\vcpkg.exe" install --triplet x64-windows
if errorlevel 1 goto :deps_error

rem ---------- choose VS generator ----------
set "CMAKE_VS_GENERATOR=Visual Studio 17 2022"
if exist "%VSWHERE%" (
    set "VS_VERSION="
    for /f "usebackq tokens=*" %%V in (`"%VSWHERE%" -latest -property installationVersion`) do set "VS_VERSION=%%V"
    if defined VS_VERSION (
        if "!VS_VERSION:~0,2!"=="18" set "CMAKE_VS_GENERATOR=Visual Studio 18 2026"
    )
)

echo.
echo [BUILD] Generator: %CMAKE_VS_GENERATOR%
echo [BUILD] Cleaning old CMake cache...
if exist "build\CMakeCache.txt" del /q "build\CMakeCache.txt" >nul 2>&1
if exist "build\CMakeFiles" rmdir /s /q "build\CMakeFiles" >nul 2>&1
if not exist build mkdir build

echo [BUILD] Configuring...
cmake -S . -B build ^
  -G "%CMAKE_VS_GENERATOR%" -A x64 ^
  -DCMAKE_PREFIX_PATH="%QT_ROOT%" ^
  -DCMAKE_TOOLCHAIN_FILE="%VCPKG_ROOT%\scripts\buildsystems\vcpkg.cmake" ^
  -DVCPKG_TARGET_TRIPLET=x64-windows
if errorlevel 1 goto :configure_error

echo.
echo [BUILD] Compiling Release...
cmake --build build --config Release --parallel 2
if errorlevel 1 goto :build_error

rem ---------- deploy ----------
echo.
echo [DEPLOY] Preparing portable folders...
set "DIST=%CD%\dist"
if exist "%DIST%\teacher" rmdir /s /q "%DIST%\teacher"
if exist "%DIST%\student" rmdir /s /q "%DIST%\student"
mkdir "%DIST%\teacher"
mkdir "%DIST%\student"

copy /Y "build\Release\ClassroomTeacher.exe" "%DIST%\teacher\" >nul
copy /Y "build\Release\ClassroomStudent.exe" "%DIST%\student\" >nul

echo [BUILD] Building local classroom-server.exe...
pushd "..\server"
set "CGO_ENABLED=0"
set "GOOS=windows"
set "GOARCH=amd64"

echo [BUILD] Resolving Go modules...
go mod tidy
if errorlevel 1 (
    popd
    goto :server_deps_error
)

go mod download
if errorlevel 1 (
    popd
    goto :server_deps_error
)

go build -mod=mod -trimpath -ldflags="-s -w" -o "%DIST%\teacher\classroom-server.exe" .
if errorlevel 1 (
    popd
    goto :server_build_error
)
popd

"%QT_ROOT%\bin\windeployqt.exe" --release --no-translations "%DIST%\teacher\ClassroomTeacher.exe"
if errorlevel 1 goto :deploy_error
"%QT_ROOT%\bin\windeployqt.exe" --release --no-translations "%DIST%\student\ClassroomStudent.exe"
if errorlevel 1 goto :deploy_error

for %%D in (
    "%CD%\vcpkg_installed\x64-windows\bin"
    "%VCPKG_ROOT%\installed\x64-windows\bin"
) do (
    if exist "%%~D" (
        copy /Y "%%~D\*.dll" "%DIST%\teacher\" >nul 2>&1
        copy /Y "%%~D\*.dll" "%DIST%\student\" >nul 2>&1
    )
)

rem ---------- zip packages ----------
where powershell >nul 2>&1
if not errorlevel 1 (
    echo [DEPLOY] Creating ZIP archives...
    powershell -NoProfile -ExecutionPolicy Bypass -Command ^
      "$ErrorActionPreference='Stop';" ^
      "$d='%DIST%';" ^
      "Remove-Item \"$d\ClassroomTeacher-Windows.zip\" -Force -ErrorAction SilentlyContinue;" ^
      "Remove-Item \"$d\ClassroomStudent-Windows.zip\" -Force -ErrorAction SilentlyContinue;" ^
      "Compress-Archive -Path \"$d\teacher\*\" -DestinationPath \"$d\ClassroomTeacher-Windows.zip\" -CompressionLevel Optimal;" ^
      "Compress-Archive -Path \"$d\student\*\" -DestinationPath \"$d\ClassroomStudent-Windows.zip\" -CompressionLevel Optimal"
)

echo.
echo ============================================================
echo   BUILD COMPLETE
echo ============================================================
echo.
echo Teacher:
echo   %DIST%\teacher\ClassroomTeacher.exe
echo.
echo Student:
echo   %DIST%\student\ClassroomStudent.exe
echo.
echo Local server bundled with Teacher:
echo   %DIST%\teacher\classroom-server.exe
echo.
echo ZIP:
echo   %DIST%\ClassroomTeacher-Windows.zip
echo   %DIST%\ClassroomStudent-Windows.zip
echo.
echo You can copy the whole teacher/student folder to another PC.
echo.
pause
exit /b 0

:git_error
echo [ERROR] Git installation failed.
goto :fail

:cmake_error
echo [ERROR] CMake installation failed.
goto :fail

:go_error
echo [ERROR] Go installation failed.
goto :fail

:server_deps_error
echo [ERROR] Go module dependencies could not be resolved.
echo Check Internet access for the first build, then run this BAT again.
goto :fail

:server_build_error
echo [ERROR] classroom-server.exe build failed.
goto :fail

:vs_error
echo [ERROR] Visual Studio C++ Build Tools were not detected after installation.
echo Reboot Windows and run this BAT again.
goto :fail

:python_error
echo [ERROR] Python installation failed.
goto :fail

:aqt_error
echo [ERROR] Failed to install aqtinstall.
goto :fail

:qt_error
echo [ERROR] Qt %QT_VERSION% MSVC 2022 x64 installation failed.
goto :fail

:vcpkg_error
echo [ERROR] vcpkg setup failed.
goto :fail

:deps_error
echo [ERROR] vcpkg dependencies failed to install.
goto :fail

:configure_error
echo [ERROR] CMake configuration failed.
goto :fail

:build_error
echo [ERROR] Compilation failed.
goto :fail

:deploy_error
echo [ERROR] Runtime deployment failed.
goto :fail

:fail
echo.
echo Setup/build stopped because of an error.
echo Copy the last error messages from this window and send them to me.
echo.
pause
exit /b 1
