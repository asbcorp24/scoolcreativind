#include "SciTheme.h"

#include <QApplication>
#include <QPalette>

namespace {
QString qss() {
    return QString::fromUtf8(R"QSS(
* {
    font-family: "Segoe UI", "Inter", sans-serif;
    font-size: 13px;
    color: #f5f7ff;
}

QMainWindow, QWidget {
    background: #06070b;
}

QLabel {
    background: transparent;
    color: #f5f7ff;
}

QLabel#muted, QLabel[muted="true"] {
    color: #9297a8;
}

QGroupBox {
    border: 1px solid rgba(255,255,255,28);
    border-radius: 16px;
    margin-top: 10px;
    padding-top: 14px;
    background: #0d1017;
    font-weight: 700;
}
QGroupBox::title {
    subcontrol-origin: margin;
    left: 14px;
    padding: 0 6px;
    color: #cfd5e2;
}

QPushButton {
    min-height: 36px;
    padding: 0 16px;
    border-radius: 18px;
    border: 1px solid rgba(255,255,255,32);
    background: #0d1017;
    color: #f5f7ff;
    font-weight: 700;
}
QPushButton:hover {
    border-color: #00e5ff;
    background: #111722;
}
QPushButton:pressed {
    background: #151a25;
}
QPushButton:disabled {
    color: #596173;
    border-color: rgba(255,255,255,18);
    background: #090b10;
}
QPushButton[accent="primary"] {
    border: 0;
    background: qlineargradient(x1:0,y1:0,x2:1,y2:0,
                                stop:0 #8a5cff, stop:1 #ff4fd8);
    color: white;
}
QPushButton[accent="primary"]:hover {
    background: qlineargradient(x1:0,y1:0,x2:1,y2:0,
                                stop:0 #9b72ff, stop:1 #ff68df);
}
QPushButton[accent="cyan"] {
    border: 1px solid rgba(0,229,255,110);
    background: rgba(0,229,255,18);
    color: #dffcff;
}
QPushButton[accent="danger"] {
    border: 1px solid rgba(255,79,216,110);
    background: rgba(255,79,216,18);
    color: #ffd8f6;
}
QPushButton[accent="success"] {
    border: 1px solid rgba(184,255,92,95);
    background: rgba(184,255,92,16);
    color: #e8ffc8;
}

QLineEdit, QTextEdit, QPlainTextEdit, QComboBox, QSpinBox, QDoubleSpinBox, QDateTimeEdit {
    min-height: 34px;
    border: 1px solid rgba(255,255,255,28);
    border-radius: 12px;
    padding: 6px 10px;
    background: #0a0d13;
    color: #f5f7ff;
    selection-background-color: #8a5cff;
}
QLineEdit:focus, QTextEdit:focus, QPlainTextEdit:focus, QComboBox:focus,
QSpinBox:focus, QDoubleSpinBox:focus, QDateTimeEdit:focus {
    border: 1px solid rgba(0,229,255,150);
}
QLineEdit::placeholder, QTextEdit::placeholder, QPlainTextEdit::placeholder {
    color: #62697a;
}

QComboBox::drop-down {
    border: 0;
    width: 28px;
}
QComboBox QAbstractItemView {
    background: #0d1017;
    color: #f5f7ff;
    selection-background-color: #1a2030;
    border: 1px solid rgba(255,255,255,30);
}

QTabWidget::pane {
    border: 1px solid rgba(255,255,255,26);
    border-radius: 18px;
    background: #0c0f16;
    top: -1px;
}
QTabBar::tab {
    min-height: 38px;
    padding: 0 16px;
    margin-right: 4px;
    border: 1px solid transparent;
    border-radius: 12px 12px 0 0;
    background: #0a0d13;
    color: #9297a8;
    font-weight: 700;
}
QTabBar::tab:hover {
    color: #f5f7ff;
    border-color: rgba(0,229,255,60);
}
QTabBar::tab:selected {
    color: #ffffff;
    border-color: rgba(138,92,255,120);
    background: qlineargradient(x1:0,y1:0,x2:1,y2:0,
                                stop:0 rgba(138,92,255,45),
                                stop:1 rgba(0,229,255,22));
}

QListWidget, QTreeWidget, QTableWidget, QTableView, QTreeView {
    border: 1px solid rgba(255,255,255,24);
    border-radius: 14px;
    background: #0a0d13;
    alternate-background-color: #0d1017;
    color: #f5f7ff;
    outline: none;
}
QListWidget::item, QTreeView::item, QTableView::item {
    padding: 8px;
    border-radius: 8px;
}
QListWidget::item:hover, QTreeView::item:hover {
    background: rgba(0,229,255,16);
}
QListWidget::item:selected, QTreeView::item:selected, QTableView::item:selected {
    background: rgba(138,92,255,48);
    color: #ffffff;
}

QHeaderView::section {
    background: #0d1017;
    color: #cfd5e2;
    border: 0;
    border-bottom: 1px solid rgba(255,255,255,24);
    padding: 9px;
    font-weight: 700;
}

QRadioButton, QCheckBox {
    spacing: 8px;
    color: #f5f7ff;
}
QRadioButton::indicator, QCheckBox::indicator {
    width: 18px;
    height: 18px;
}
QRadioButton::indicator:unchecked {
    border: 2px solid #596173;
    border-radius: 9px;
    background: #0a0d13;
}
QRadioButton::indicator:checked {
    border: 5px solid #8a5cff;
    border-radius: 9px;
    background: #00e5ff;
}
QCheckBox::indicator:unchecked {
    border: 1px solid #596173;
    border-radius: 5px;
    background: #0a0d13;
}
QCheckBox::indicator:checked {
    border: 1px solid #8a5cff;
    border-radius: 5px;
    background: #8a5cff;
}

QScrollBar:vertical {
    background: #080a0f;
    width: 10px;
    margin: 2px;
}
QScrollBar::handle:vertical {
    min-height: 28px;
    border-radius: 5px;
    background: #30384a;
}
QScrollBar::handle:vertical:hover {
    background: #8a5cff;
}
QScrollBar::add-line:vertical, QScrollBar::sub-line:vertical {
    height: 0;
}
QScrollBar:horizontal {
    background: #080a0f;
    height: 10px;
    margin: 2px;
}
QScrollBar::handle:horizontal {
    min-width: 28px;
    border-radius: 5px;
    background: #30384a;
}
QScrollBar::handle:horizontal:hover {
    background: #00e5ff;
}
QScrollBar::add-line:horizontal, QScrollBar::sub-line:horizontal {
    width: 0;
}

QSplitter::handle {
    background: rgba(255,255,255,18);
}
QSplitter::handle:hover {
    background: rgba(0,229,255,90);
}

QProgressBar {
    border: 1px solid rgba(255,255,255,24);
    border-radius: 8px;
    background: #0a0d13;
    text-align: center;
    color: #f5f7ff;
}
QProgressBar::chunk {
    border-radius: 7px;
    background: qlineargradient(x1:0,y1:0,x2:1,y2:0,
                                stop:0 #00e5ff,
                                stop:.52 #8a5cff,
                                stop:1 #ff4fd8);
}

QToolTip {
    border: 1px solid rgba(0,229,255,80);
    background: #0d1017;
    color: #f5f7ff;
    padding: 7px 9px;
}

QMessageBox, QDialog {
    background: #0d1017;
}

QStatusBar {
    background: #080a0f;
    color: #9297a8;
}

/* Named surfaces */
QWidget#sciCard {
    background: #0d1017;
    border: 1px solid rgba(255,255,255,26);
    border-radius: 18px;
}
QWidget#sciTopBar {
    background: #080a0f;
    border-bottom: 1px solid rgba(255,255,255,22);
}
QLabel#sciTitle {
    font-size: 20px;
    font-weight: 800;
    color: #ffffff;
}
QLabel#sciEyebrow {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 2px;
    color: #00e5ff;
}
QLabel#sciStatusGood {
    color: #b8ff5c;
    font-weight: 700;
}
QLabel#sciStatusWarn {
    color: #ffcf5c;
    font-weight: 700;
}
QLabel#sciStatusBad {
    color: #ff4fd8;
    font-weight: 700;
}
)QSS");
}
}

namespace SciTheme {

void apply(QApplication& app) {
    QPalette palette;
    palette.setColor(QPalette::Window, QColor("#06070b"));
    palette.setColor(QPalette::WindowText, QColor("#f5f7ff"));
    palette.setColor(QPalette::Base, QColor("#0a0d13"));
    palette.setColor(QPalette::AlternateBase, QColor("#0d1017"));
    palette.setColor(QPalette::Text, QColor("#f5f7ff"));
    palette.setColor(QPalette::Button, QColor("#0d1017"));
    palette.setColor(QPalette::ButtonText, QColor("#f5f7ff"));
    palette.setColor(QPalette::Highlight, QColor("#8a5cff"));
    palette.setColor(QPalette::HighlightedText, QColor("#ffffff"));
    palette.setColor(QPalette::PlaceholderText, QColor("#62697a"));
    app.setPalette(palette);
    app.setStyle("Fusion");
    app.setStyleSheet(qss());
}

}
