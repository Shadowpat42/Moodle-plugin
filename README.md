# Вас приветствует команда ИП Ворожцов <img src="https://media.tenor.com/dMwtTTN4XusAAAAj/yeah-cute.gif" width="100" height="100" />
```zsh
> print("Hello my friend!")
```

### Состав команды:

| Участник                          | Роль                  | 
| --------------------------------- | --------------------- | 
| Ворожцов Антон Александрович      | Тимлид                |
| Зубова Екатерина Александровна    | Дизайнер              |
| Джобиров Владимир Джамшедович     | Разработчик, backend  | 
| Азанов Андрей Александрович       | Разработчик, frontend |

### Cтруктура проекта:

```text
├──  statistics/               # Основная директория плагина
│   │
│   ├── db/                   # Директория для работы с базой данных
│   │   └── access.php        # Определение прав доступа и ролей пользователей
│   │
│   ├── active_users.php      # Статистика активных пользователей курса
│   ├── discussion_posts.php  # Статистика сообщений пользователей на форуме
│   ├── index.php             # Главная страница плагина с обзорной статистикой
│   ├── lib.php               # Общие функции для работы с данными и отображения
│   ├── progress_users.php    # Прогресс пользователей по курсу (завершенные задачи и тесты)
│   ├── styles.css            # Стили для визуализации статистики
│   ├── time_spent.php        # Информация о времени, проведенном пользователями на курсе
│   ├── Vector.svg            # Векторное изображение, используемое в интерфейсе плагина
│   └── visits_users.php      # Статистика посещений пользователями курса

```

**Артефакты дизайнера:**
- [Ссылка на фигму](https://www.figma.com/proto/StSvQ8Eqbvp604qzbbwqG5/Untitled?node-id=0-1&t=ND6mMvPbC6tFCmJl-1)



### Установка и настройка

1. Скачайте репозиторий.
2. Поместите плагин в соответствующую папку Moodle: `moodle/local/your_plugin`.
3. Перейдите в админку Moodle и активируйте плагин.
4. Настройте права доступа в соответствии с вашими требованиями.


