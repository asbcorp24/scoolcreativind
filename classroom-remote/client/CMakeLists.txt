cmake_minimum_required(VERSION 3.24)
project(ClassroomRemote LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 20)
set(CMAKE_CXX_STANDARD_REQUIRED ON)
set(CMAKE_AUTOMOC ON)
set(CMAKE_AUTOUIC ON)
set(CMAKE_AUTORCC ON)

include(FetchContent)

find_package(Qt6 6.5 REQUIRED COMPONENTS Core Gui Widgets Network WebSockets Multimedia)

# Native WebRTC transport. We only need DataChannels here; video/audio frames are
# encoded by Qt and relayed through the WebRTC SCTP channel.
set(NO_MEDIA ON CACHE BOOL "" FORCE)
set(NO_EXAMPLES ON CACHE BOOL "" FORCE)
set(NO_TESTS ON CACHE BOOL "" FORCE)
set(BUILD_SHARED_LIBS OFF CACHE BOOL "" FORCE)
set(USE_MBEDTLS ON CACHE BOOL "" FORCE)

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
)
target_link_libraries(classroom_common
    PUBLIC
        Qt6::Core
        Qt6::Network
        Qt6::WebSockets
        Qt6::Multimedia
        LibDataChannel::LibDataChannelStatic
)
target_include_directories(classroom_common PUBLIC ${CMAKE_CURRENT_SOURCE_DIR})

add_executable(ClassroomTeacher
    teacher/main.cpp
    teacher/TeacherWindow.cpp
    teacher/TeacherWindow.h
    teacher/RemoteView.cpp
    teacher/RemoteView.h
)
target_link_libraries(ClassroomTeacher PRIVATE classroom_common Qt6::Gui Qt6::Widgets Qt6::Network Qt6::Multimedia)

add_executable(ClassroomStudent
    student/main.cpp
    student/StudentWindow.cpp
    student/StudentWindow.h
    student/WinRemoteInput.cpp
    student/WinRemoteInput.h
)
target_link_libraries(ClassroomStudent PRIVATE classroom_common Qt6::Gui Qt6::Widgets Qt6::Network Qt6::Multimedia)
if(WIN32)
    target_link_libraries(ClassroomStudent PRIVATE user32)
endif()
