-- phpMyAdmin SQL Dump
-- version 4.9.5deb2
-- https://www.phpmyadmin.net/
--
-- Хост: localhost:3306
-- Время создания: Апр 04 2021 г., 02:36
-- Версия сервера: 8.0.23-0ubuntu0.20.04.1
-- Версия PHP: 7.4.3

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- База данных: `hs`
--

-- --------------------------------------------------------

--
-- Структура таблицы `images`
--

CREATE TABLE `images` (
  `obj_id` int UNSIGNED NOT NULL,
  `obj_type` enum('persons','masters_services','salons') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `subtype` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `file_name` char(8) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `img_type` enum('jpg','png') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `original_file_name` varchar(222) NOT NULL,
  `status` enum('active','deleted') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `masters_schedule`
--

CREATE TABLE `masters_schedule` (
  `id` int UNSIGNED NOT NULL,
  `shift_id` int UNSIGNED NOT NULL,
  `service_id` int UNSIGNED NOT NULL,
  `begin_minutes` smallint UNSIGNED NOT NULL,
  `duration_minutes` smallint UNSIGNED NOT NULL,
  `s_type` enum('own','external','pause') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `comment` varchar(222) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''''''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `masters_services`
--

CREATE TABLE `masters_services` (
  `person_id` int UNSIGNED NOT NULL,
  `salon_service_id` int UNSIGNED NOT NULL,
  `price_default` smallint UNSIGNED DEFAULT NULL,
  `duration_default` smallint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `persons`
--

CREATE TABLE `persons` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(111) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `requests_to_salons`
--

CREATE TABLE `requests_to_salons` (
  `id` int UNSIGNED NOT NULL,
  `salon_id` int UNSIGNED NOT NULL,
  `service_id` int UNSIGNED NOT NULL,
  `desired_time` timestamp NOT NULL,
  `status` enum('proposed','accepted','rejected','conflicting','timeout') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `comment` varchar(333) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `salons`
--

CREATE TABLE `salons` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(111) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `external_id` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `salons_services`
--

CREATE TABLE `salons_services` (
  `id` int UNSIGNED NOT NULL,
  `salon_id` int UNSIGNED NOT NULL,
  `service_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `price_default` smallint UNSIGNED DEFAULT NULL,
  `duration_default` smallint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `salon_masters`
--

CREATE TABLE `salon_masters` (
  `id` int UNSIGNED NOT NULL,
  `salon_id` int UNSIGNED NOT NULL,
  `person_id` int UNSIGNED NOT NULL,
  `roles` set('ordinary','admin','requested','rejected') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'requested'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `services`
--

CREATE TABLE `services` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(101) NOT NULL,
  `parent_service` int UNSIGNED DEFAULT NULL,
  `adding_salon` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `services`
--

INSERT INTO `services` (`id`, `name`, `parent_service`, `adding_salon`) VALUES
(1, 'Мужские стрижки', NULL, NULL),
(2, 'Женские стрижки и укладки', NULL, NULL),
(3, 'Окраска волос', NULL, NULL),
(4, 'Массаж', NULL, NULL),
(5, 'Маникюр, педикюр', NULL, NULL),
(6, 'Спортивная, бокс, полубокс', 1, NULL),
(7, 'Налысо', 1, NULL),
(8, 'Площадка', 1, NULL),
(9, 'Детская', 1, NULL),
(10, 'Каре', 2, NULL),
(11, 'Плетение кос', 2, NULL),
(12, 'Подравнять челку', 2, NULL),
(13, 'Креативная', 2, NULL),
(14, 'Вечерняя', 2, NULL),
(20, 'Колорирование', 3, NULL),
(21, 'Мелирование', 3, NULL),
(22, 'Омбре', 3, NULL),
(23, 'Сомбре', 3, NULL),
(25, 'Брондирование', 3, NULL),
(26, 'Тонирование', 3, NULL),
(27, 'Шатуш', 3, NULL),
(28, 'Аиртач', 3, NULL),
(29, 'Балаяж', 3, NULL),
(30, 'Элюминирование', 3, NULL),
(31, 'Деграде', 3, NULL),
(37, 'Маникюр гигиенический', 5, NULL),
(38, 'Маникюр европейский', 5, NULL),
(39, 'Ремонт ногтя', 5, NULL),
(40, 'Маникюр классический', 5, NULL),
(41, 'мужской маникюр', 5, NULL),
(42, 'Наращивание ногтей', 5, NULL),
(57, 'Тайский массаж', 4, NULL),
(58, 'Массаж горячими камнями (стоунтерапия)', 4, NULL),
(59, 'Лимфодренажный массаж', 4, NULL),
(60, 'Антицеллюлитный массаж', 4, NULL),
(61, 'Медовый массаж', 4, NULL),
(62, 'Испанский массаж', 4, NULL),
(63, 'Китайский массаж', 4, NULL),
(64, 'Турецкий мыльный массаж', 4, NULL),
(65, 'Каскад', 2, NULL),
(66, 'Оформление бороды и усов', 1, NULL),
(67, 'Наращивание волос', 2, NULL),
(68, 'Химическая завивка', 2, NULL),
(69, 'Британка', 1, NULL),
(70, 'Канадка', 1, NULL),
(71, 'Маллет', 1, NULL),
(72, 'Боб', 1, NULL),
(73, 'Цезарь', 1, NULL),
(74, 'Стрелец', 1, NULL),
(75, 'Гранж', 1, NULL),
(76, 'Топ кнот', 1, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `workshifts`
--

CREATE TABLE `workshifts` (
  `id` int UNSIGNED NOT NULL,
  `salon_id` int UNSIGNED NOT NULL,
  `master_id` int UNSIGNED NOT NULL,
  `date_begin` date NOT NULL,
  `time_begin` time NOT NULL,
  `duration_minutes` smallint UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `images`
--
ALTER TABLE `images`
  ADD UNIQUE KEY `file_name` (`file_name`),
  ADD KEY `obj_id` (`obj_id`);

--
-- Индексы таблицы `masters_schedule`
--
ALTER TABLE `masters_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shift_id` (`shift_id`);

--
-- Индексы таблицы `masters_services`
--
ALTER TABLE `masters_services`
  ADD UNIQUE KEY `person_id` (`person_id`,`salon_service_id`),
  ADD KEY `masters_services_ibfk_2` (`salon_service_id`);

--
-- Индексы таблицы `persons`
--
ALTER TABLE `persons`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `requests_to_salons`
--
ALTER TABLE `requests_to_salons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `salon_id` (`salon_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Индексы таблицы `salons`
--
ALTER TABLE `salons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `external_id` (`external_id`);

--
-- Индексы таблицы `salons_services`
--
ALTER TABLE `salons_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `salon_id` (`salon_id`,`service_id`),
  ADD KEY `salons_services_ibfk_3` (`service_id`);

--
-- Индексы таблицы `salon_masters`
--
ALTER TABLE `salon_masters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_salon` (`salon_id`,`person_id`),
  ADD KEY `id_person` (`person_id`);

--
-- Индексы таблицы `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_service` (`parent_service`),
  ADD KEY `adding_salon` (`adding_salon`);

--
-- Индексы таблицы `workshifts`
--
ALTER TABLE `workshifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `salon_id` (`salon_id`),
  ADD KEY `master_id` (`master_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `masters_schedule`
--
ALTER TABLE `masters_schedule`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `persons`
--
ALTER TABLE `persons`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `requests_to_salons`
--
ALTER TABLE `requests_to_salons`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `salons`
--
ALTER TABLE `salons`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `salons_services`
--
ALTER TABLE `salons_services`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `salon_masters`
--
ALTER TABLE `salon_masters`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `services`
--
ALTER TABLE `services`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT для таблицы `workshifts`
--
ALTER TABLE `workshifts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `masters_schedule`
--
ALTER TABLE `masters_schedule`
  ADD CONSTRAINT `masters_schedule_ibfk_1` FOREIGN KEY (`shift_id`) REFERENCES `workshifts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `masters_services`
--
ALTER TABLE `masters_services`
  ADD CONSTRAINT `masters_services_ibfk_1` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `masters_services_ibfk_2` FOREIGN KEY (`salon_service_id`) REFERENCES `salons_services` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `requests_to_salons`
--
ALTER TABLE `requests_to_salons`
  ADD CONSTRAINT `requests_to_salons_ibfk_1` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `requests_to_salons_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `salons_services`
--
ALTER TABLE `salons_services`
  ADD CONSTRAINT `salons_services_ibfk_2` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `salons_services_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `salon_masters`
--
ALTER TABLE `salon_masters`
  ADD CONSTRAINT `salon_masters_ibfk_1` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `salon_masters_ibfk_2` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`parent_service`) REFERENCES `services` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `services_ibfk_2` FOREIGN KEY (`adding_salon`) REFERENCES `salons` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `workshifts`
--
ALTER TABLE `workshifts`
  ADD CONSTRAINT `workshifts_ibfk_1` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `workshifts_ibfk_2` FOREIGN KEY (`master_id`) REFERENCES `persons` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
