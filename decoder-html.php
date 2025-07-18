#!/usr/bin/env php
<?php

// Путь к входному файлу
$inputFile = '/tmp/training.csv';

// Путь к выходному файлу
$outputFile = 'training1.csv';

// Проверка существования файла
if (!file_exists($inputFile)) {
    die("Файл '$inputFile' не найден.\n");
}

// Чтение содержимого
$inputText = file_get_contents($inputFile);

// Декодирование HTML-сущностей
$decodedText = html_entity_decode($inputText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

// Запись в выходной файл
file_put_contents($outputFile, $decodedText);

echo "Готово! Результат сохранён в '$outputFile'.\n";
