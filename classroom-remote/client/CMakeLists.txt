cmake_minimum_required(VERSION 3.24)
project(ClassroomRemote LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 20)
set(CMAKE_CXX_STANDARD_REQUIRED ON)
set(CMAKE_AUTOMOC ON)
set(CMAKE_AUTOUIC ON)
set(CMAKE_AUTORCC ON)

find_package(Qt6 6.5 REQUIRED COMPONENTS Core Gui Widgets Network WebSockets)

add_library(classroom_common
    common/WsClient.cpp
    common/WsClient.h
)
target_link_libraries(classroom_common PUBLIC Qt6::Core Qt6::Network Qt6::WebSockets)
target_include_directories(classroom_common PUBLIC ${CMAKE_CURRENT_SOURCE_DIR})

add_executable(ClassroomTeacher
    teacher/main.cpp
    teacher/TeacherWindow.cpp
    teacher/TeacherWindow.h
)
target_link_libraries(ClassroomTeacher PRIVATE classroom_common Qt6::Gui Qt6::Widgets Qt6::Network)

add_executable(ClassroomStudent
    student/main.cpp
    student/StudentWindow.cpp
    student/StudentWindow.h
    student/WinRemoteInput.cpp
    student/WinRemoteInput.h
)
target_link_libraries(ClassroomStudent PRIVATE classroom_common Qt6::Gui Qt6::Widgets Qt6::Network)
if(WIN32)
    target_link_libraries(ClassroomStudent PRIVATE user32)
endif()
