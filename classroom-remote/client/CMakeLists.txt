cmake_minimum_required(VERSION 3.24)
project(ClassroomRemote LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 20)
set(CMAKE_CXX_STANDARD_REQUIRED ON)
set(CMAKE_AUTOMOC ON)
set(CMAKE_AUTOUIC ON)
set(CMAKE_AUTORCC ON)

include(FetchContent)

find_package(Qt6 6.5 REQUIRED COMPONENTS Core Gui Widgets Network WebSockets Multimedia)

find_path(OPENH264_INCLUDE_DIR NAMES wels/codec_api.h REQUIRED)
find_library(OPENH264_LIBRARY NAMES openh264 REQUIRED)
find_path(OPUS_INCLUDE_DIR NAMES opus/opus.h REQUIRED)
find_library(OPUS_LIBRARY NAMES opus REQUIRED)

# Native WebRTC transport with RTP media tracks.
set(NO_MEDIA OFF CACHE BOOL "" FORCE)
set(NO_EXAMPLES ON CACHE BOOL "" FORCE)
set(NO_TESTS ON CACHE BOOL "" FORCE)
set(BUILD_SHARED_LIBS OFF CACHE BOOL "" FORCE)
set(USE_MBEDTLS OFF CACHE BOOL "" FORCE)
set(USE_GNUTLS OFF CACHE BOOL "" FORCE)

FetchContent_Declare(
    libdatachannel
    GIT_REPOSITORY https://github.com/paullouisageneau/libdatachannel.git
    GIT_TAG v0.24.6
    GIT_SHALLOW TRUE
    GIT_SUBMODULES_RECURSE TRUE
)
FetchContent_MakeAvailable(libdatachannel)

add_library(classroom_common
    common/WsClient.cpp
    common/WsClient.h
    common/RtcRelayClient.cpp
    common/RtcRelayClient.h
    common/AudioPipe.cpp
    common/AudioPipe.h
    common/MediaCodec.cpp
    common/MediaCodec.h
    common/InviteLink.cpp
    common/InviteLink.h
    common/LearningApi.cpp
    common/LearningApi.h
    common/AnnotationOverlay.cpp
    common/AnnotationOverlay.h
)
target_link_libraries(classroom_common
    PUBLIC
        Qt6::Core
        Qt6::Widgets
        Qt6::Network
        Qt6::WebSockets
        Qt6::Multimedia
        LibDataChannel::LibDataChannelStatic
        ${OPENH264_LIBRARY}
        ${OPUS_LIBRARY}
)
target_include_directories(classroom_common PUBLIC
    ${CMAKE_CURRENT_SOURCE_DIR}
    ${OPENH264_INCLUDE_DIR}
    ${OPUS_INCLUDE_DIR}
)

add_executable(ClassroomTeacher WIN32
    teacher/main.cpp
    teacher/TeacherWindow.cpp
    teacher/TeacherWindow.h
    teacher/RemoteView.cpp
    teacher/RemoteView.h
    teacher/TeacherLessonsWidget.cpp
    teacher/TeacherLessonsWidget.h
)
target_link_libraries(ClassroomTeacher PRIVATE classroom_common Qt6::Gui Qt6::Widgets Qt6::Network Qt6::Multimedia)

add_executable(ClassroomStudent WIN32
    student/main.cpp
    student/StudentWindow.cpp
    student/StudentWindow.h
    student/WinRemoteInput.cpp
    student/WinRemoteInput.h
    student/StudentLessonsWidget.cpp
    student/StudentLessonsWidget.h
    student/ScreenshotCapture.cpp
    student/ScreenshotCapture.h
)
target_link_libraries(ClassroomStudent PRIVATE classroom_common Qt6::Gui Qt6::Widgets Qt6::Network Qt6::Multimedia)
if(WIN32)
    target_link_libraries(ClassroomStudent PRIVATE user32)
endif()


enable_testing()
add_executable(MediaCodecSmoke tests/MediaCodecSmoke.cpp)
target_link_libraries(MediaCodecSmoke PRIVATE classroom_common Qt6::Gui)
add_test(NAME MediaCodecSmoke COMMAND MediaCodecSmoke)
