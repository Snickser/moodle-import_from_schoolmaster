#!/bin/bash

CSV_FILE="файл4.csv"
PHOTO_DIR="uploads/person/photo"

# Проверка наличия файла и папки
if [[ ! -f "$CSV_FILE" ]]; then
    echo "CSV-файл '$CSV_FILE' не найден"
    exit 1
fi

if [[ ! -d "$PHOTO_DIR" ]]; then
    echo "Папка с фотографиями '$PHOTO_DIR' не найдена"
    exit 1
fi

# Чтение CSV построчно, пропуская заголовок
tail -n +2 "$CSV_FILE" | while IFS=',' read -r idnumber photo; do
    idnumber=$(echo "$idnumber" | xargs) # удаляет пробелы
    photo=$(echo "$photo" | xargs)

    original_path="$PHOTO_DIR/$photo"

    if [[ ! -f "$original_path" ]]; then
        echo "Файл не найден: $photo"
        continue
    fi

    # Получить расширение
    extension="${photo##*.}"
    new_filename="${idnumber}.${extension}"
    new_path="pict/$new_filename"

    cp "$original_path" "$new_path"
    echo "Переименовано: $photo -> $new_filename"
done
