

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";



-- Banco de dados: `decolae`

--
DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reservar_passagem` (IN `p_cliente_cpf` CHAR(11), IN `p_voo_id` INT UNSIGNED, IN `p_assento_id` INT UNSIGNED, IN `p_valor` DECIMAL(10,2))   BEGIN
    DECLARE v_aeronave_voo INT UNSIGNED;
    DECLARE v_aeronave_assento INT UNSIGNED;
    DECLARE v_status VARCHAR(20);
    DECLARE v_bilhete CHAR(10);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
    START TRANSACTION;
    SELECT aeronave_id INTO v_aeronave_voo FROM voo WHERE id = p_voo_id FOR UPDATE;
    SELECT aeronave_id INTO v_aeronave_assento FROM assento WHERE id = p_assento_id FOR UPDATE;
    IF v_aeronave_voo IS NULL OR v_aeronave_assento IS NULL OR v_aeronave_voo <> v_aeronave_assento THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Assento incompativel com o voo.';
    END IF;
    SELECT status INTO v_status FROM passagem WHERE voo_id = p_voo_id AND assento_id = p_assento_id LIMIT 1;
    IF v_status IS NOT NULL AND v_status <> 'cancelada' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Assento ja reservado por outra operacao.';
    END IF;
    SET v_bilhete = UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 10));
    INSERT INTO passagem (numero_bilhete, cliente_cpf, voo_id, assento_id, valor) VALUES (v_bilhete, p_cliente_cpf, p_voo_id, p_assento_id, p_valor);
    INSERT INTO log (usuario_cpf, acao, descricao, registro_afetado) VALUES (p_cliente_cpf, 'RESERVA_CRIADA', CONCAT('Passagem ', v_bilhete, ' confirmada.'), v_bilhete);
    COMMIT;
    SELECT v_bilhete AS numero_bilhete;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `admin`
--

CREATE TABLE `admin` (
  `cpf` char(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `admin`
--

INSERT INTO `admin` (`cpf`) VALUES
('12345678901');

-- --------------------------------------------------------

--
-- Estrutura para tabela `aeronave`
--

CREATE TABLE `aeronave` (
  `id` int(10) UNSIGNED NOT NULL,
  `identificacao` varchar(20) NOT NULL,
  `modelo` varchar(80) NOT NULL,
  `fileiras` smallint(5) UNSIGNED NOT NULL,
  `assentos_por_fileira` tinyint(3) UNSIGNED NOT NULL,
  `gerente_cpf` char(11) NOT NULL,
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `versao` int(10) UNSIGNED DEFAULT 1
) ;

--
-- Despejando dados para a tabela `aeronave`
--

INSERT INTO `aeronave` (`id`, `identificacao`, `modelo`, `fileiras`, `assentos_por_fileira`, `gerente_cpf`, `data_atualizacao`, `versao`) VALUES
(1, 'PT-ABC', 'Boeing 737', 30, 6, '12345678902', '2026-11-21 16:21:58', 1),
(2, 'PT-DEF', 'Airbus A320', 32, 6, '12345678902', '2026-11-21 16:21:58', 1),
(3, 'PT-945', 'Boeing 321', 3, 3, '12345678902', '2026-11-21 18:56:12', 1);

--
-- Acionadores `aeronave`
--
DELIMITER $$
CREATE TRIGGER `trg_aeronave_incrementar_versao` BEFORE UPDATE ON `aeronave` FOR EACH ROW SET NEW.versao = OLD.versao + 1
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `assento`
--

CREATE TABLE `assento` (
  `id` int(10) UNSIGNED NOT NULL,
  `aeronave_id` int(10) UNSIGNED NOT NULL,
  `codigo_assento` varchar(5) NOT NULL,
  `numero_fileira` smallint(5) UNSIGNED NOT NULL,
  `letra` char(1) NOT NULL,
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `versao` int(10) UNSIGNED DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `assento`
--

INSERT INTO `assento` (`id`, `aeronave_id`, `codigo_assento`, `numero_fileira`, `letra`, `data_atualizacao`, `versao`) VALUES
(1, 1, '1A', 1, 'A', '2026-11-21 16:21:58', 1),
(2, 1, '1B', 1, 'B', '2026-11-21 16:21:58', 1),
(3, 1, '1C', 1, 'C', '2026-11-21 16:21:58', 1),
(4, 1, '1D', 1, 'D', '2026-11-21 16:21:58', 1),
(5, 1, '1E', 1, 'E', '2026-11-21 16:21:58', 1),
(6, 1, '1F', 1, 'F', '2026-11-21 16:21:58', 1),
(7, 1, '2A', 2, 'A', '2026-11-21 16:21:58', 1),
(8, 1, '2B', 2, 'B', '2026-11-21 16:21:58', 1),
(9, 1, '2C', 2, 'C', '2026-11-21 16:21:58', 1),
(10, 1, '2D', 2, 'D', '2026-11-21 16:21:58', 1),
(11, 1, '2E', 2, 'E', '2026-11-21 16:21:58', 1),
(12, 1, '2F', 2, 'F', '2026-11-21 16:21:58', 1),
(13, 2, '1A', 1, 'A', '2026-11-21 16:21:58', 1),
(14, 2, '1B', 1, 'B', '2026-11-21 16:21:58', 1),
(15, 2, '1C', 1, 'C', '2026-11-21 16:21:58', 1),
(16, 2, '1D', 1, 'D', '2026-11-21 16:21:58', 1),
(17, 2, '1E', 1, 'E', '2026-11-21 16:21:58', 1),
(18, 2, '1F', 1, 'F', '2026-11-21 16:21:58', 1),
(19, 2, '2A', 2, 'A', '2026-11-21 16:21:58', 1),
(20, 2, '2B', 2, 'B', '2026-11-21 16:21:58', 1),
(21, 2, '2C', 2, 'C', '2026-11-21 16:21:58', 1),
(22, 2, '2D', 2, 'D', '2026-11-21 16:21:58', 1),
(23, 2, '2E', 2, 'E', '2026-11-21 16:21:58', 1),
(24, 2, '2F', 2, 'F', '2026-11-21 16:21:58', 1),
(25, 1, '3A', 3, 'A', '2026-11-21 18:11:38', 1),
(26, 1, '3B', 3, 'B', '2026-11-21 18:11:38', 1),
(27, 1, '3C', 3, 'C', '2026-11-21 18:11:38', 1),
(28, 1, '3D', 3, 'D', '2026-11-21 18:11:38', 1),
(29, 1, '3E', 3, 'E', '2026-11-21 18:11:38', 1),
(30, 1, '3F', 3, 'F', '2026-11-21 18:11:38', 1),
(31, 1, '4A', 4, 'A', '2026-11-21 18:11:38', 1),
(32, 1, '4B', 4, 'B', '2026-11-21 18:11:38', 1),
(33, 1, '4C', 4, 'C', '2026-11-21 18:11:38', 1),
(34, 1, '4D', 4, 'D', '2026-11-21 18:11:38', 1),
(35, 1, '4E', 4, 'E', '2026-11-21 18:11:38', 1),
(36, 1, '4F', 4, 'F', '2026-11-21 18:11:38', 1),
(37, 1, '5A', 5, 'A', '2026-11-21 18:11:38', 1),
(38, 1, '5B', 5, 'B', '2026-11-21 18:11:38', 1),
(39, 1, '5C', 5, 'C', '2026-11-21 18:11:38', 1),
(40, 1, '5D', 5, 'D', '2026-11-21 18:11:38', 1),
(41, 1, '5E', 5, 'E', '2026-11-21 18:11:38', 1),
(42, 1, '5F', 5, 'F', '2026-11-21 18:11:38', 1),
(43, 1, '6A', 6, 'A', '2026-11-21 18:11:38', 1),
(44, 1, '6B', 6, 'B', '2026-11-21 18:11:38', 1),
(45, 1, '6C', 6, 'C', '2026-11-21 18:11:38', 1),
(46, 1, '6D', 6, 'D', '2026-11-21 18:11:38', 1),
(47, 1, '6E', 6, 'E', '2026-11-21 18:11:38', 1),
(48, 1, '6F', 6, 'F', '2026-11-21 18:11:38', 1),
(49, 1, '7A', 7, 'A', '2026-11-21 18:11:38', 1),
(50, 1, '7B', 7, 'B', '2026-11-21 18:11:38', 1),
(51, 1, '7C', 7, 'C', '2026-11-21 18:11:38', 1),
(52, 1, '7D', 7, 'D', '2026-11-21 18:11:38', 1),
(53, 1, '7E', 7, 'E', '2026-11-21 18:11:38', 1),
(54, 1, '7F', 7, 'F', '2026-11-21 18:11:38', 1),
(55, 1, '8A', 8, 'A', '2026-11-21 18:11:38', 1),
(56, 1, '8B', 8, 'B', '2026-11-21 18:11:38', 1),
(57, 1, '8C', 8, 'C', '2026-11-21 18:11:38', 1),
(58, 1, '8D', 8, 'D', '2026-11-21 18:11:38', 1),
(59, 1, '8E', 8, 'E', '2026-11-21 18:11:38', 1),
(60, 1, '8F', 8, 'F', '2026-11-21 18:11:38', 1),
(61, 1, '9A', 9, 'A', '2026-11-21 18:11:38', 1),
(62, 1, '9B', 9, 'B', '2026-11-21 18:11:38', 1),
(63, 1, '9C', 9, 'C', '2026-11-21 18:11:38', 1),
(64, 1, '9D', 9, 'D', '2026-11-21 18:11:38', 1),
(65, 1, '9E', 9, 'E', '2026-11-21 18:11:38', 1),
(66, 1, '9F', 9, 'F', '2026-11-21 18:11:38', 1),
(67, 1, '10A', 10, 'A', '2026-11-21 18:11:38', 1),
(68, 1, '10B', 10, 'B', '2026-11-21 18:11:38', 1),
(69, 1, '10C', 10, 'C', '2026-11-21 18:11:38', 1),
(70, 1, '10D', 10, 'D', '2026-11-21 18:11:38', 1),
(71, 1, '10E', 10, 'E', '2026-11-21 18:11:38', 1),
(72, 1, '10F', 10, 'F', '2026-11-21 18:11:38', 1),
(73, 1, '11A', 11, 'A', '2026-11-21 18:11:38', 1),
(74, 1, '11B', 11, 'B', '2026-11-21 18:11:38', 1),
(75, 1, '11C', 11, 'C', '2026-11-21 18:11:38', 1),
(76, 1, '11D', 11, 'D', '2026-11-21 18:11:38', 1),
(77, 1, '11E', 11, 'E', '2026-11-21 18:11:38', 1),
(78, 1, '11F', 11, 'F', '2026-11-21 18:11:38', 1),
(79, 1, '12A', 12, 'A', '2026-11-21 18:11:38', 1),
(80, 1, '12B', 12, 'B', '2026-11-21 18:11:38', 1),
(81, 1, '12C', 12, 'C', '2026-11-21 18:11:38', 1),
(82, 1, '12D', 12, 'D', '2026-11-21 18:11:38', 1),
(83, 1, '12E', 12, 'E', '2026-11-21 18:11:38', 1),
(84, 1, '12F', 12, 'F', '2026-11-21 18:11:38', 1),
(85, 1, '13A', 13, 'A', '2026-11-21 18:11:38', 1),
(86, 1, '13B', 13, 'B', '2026-11-21 18:11:38', 1),
(87, 1, '13C', 13, 'C', '2026-11-21 18:11:38', 1),
(88, 1, '13D', 13, 'D', '2026-11-21 18:11:38', 1),
(89, 1, '13E', 13, 'E', '2026-11-21 18:11:38', 1),
(90, 1, '13F', 13, 'F', '2026-11-21 18:11:38', 1),
(91, 1, '14A', 14, 'A', '2026-11-21 18:11:38', 1),
(92, 1, '14B', 14, 'B', '2026-11-21 18:11:38', 1),
(93, 1, '14C', 14, 'C', '2026-11-21 18:11:38', 1),
(94, 1, '14D', 14, 'D', '2026-11-21 18:11:38', 1),
(95, 1, '14E', 14, 'E', '2026-11-21 18:11:38', 1),
(96, 1, '14F', 14, 'F', '2026-11-21 18:11:38', 1),
(97, 1, '15A', 15, 'A', '2026-11-21 18:11:38', 1),
(98, 1, '15B', 15, 'B', '2026-11-21 18:11:38', 1),
(99, 1, '15C', 15, 'C', '2026-11-21 18:11:38', 1),
(100, 1, '15D', 15, 'D', '2026-11-21 18:11:38', 1),
(101, 1, '15E', 15, 'E', '2026-11-21 18:11:38', 1),
(102, 1, '15F', 15, 'F', '2026-11-21 18:11:38', 1),
(103, 1, '16A', 16, 'A', '2026-11-21 18:11:38', 1),
(104, 1, '16B', 16, 'B', '2026-11-21 18:11:38', 1),
(105, 1, '16C', 16, 'C', '2026-11-21 18:11:38', 1),
(106, 1, '16D', 16, 'D', '2026-11-21 18:11:38', 1),
(107, 1, '16E', 16, 'E', '2026-11-21 18:11:38', 1),
(108, 1, '16F', 16, 'F', '2026-11-21 18:11:38', 1),
(109, 1, '17A', 17, 'A', '2026-11-21 18:11:38', 1),
(110, 1, '17B', 17, 'B', '2026-11-21 18:11:38', 1),
(111, 1, '17C', 17, 'C', '2026-11-21 18:11:38', 1),
(112, 1, '17D', 17, 'D', '2026-11-21 18:11:38', 1),
(113, 1, '17E', 17, 'E', '2026-11-21 18:11:38', 1),
(114, 1, '17F', 17, 'F', '2026-11-21 18:11:38', 1),
(115, 1, '18A', 18, 'A', '2026-11-21 18:11:38', 1),
(116, 1, '18B', 18, 'B', '2026-11-21 18:11:38', 1),
(117, 1, '18C', 18, 'C', '2026-11-21 18:11:38', 1),
(118, 1, '18D', 18, 'D', '2026-11-21 18:11:38', 1),
(119, 1, '18E', 18, 'E', '2026-11-21 18:11:38', 1),
(120, 1, '18F', 18, 'F', '2026-11-21 18:11:38', 1),
(121, 1, '19A', 19, 'A', '2026-11-21 18:11:38', 1),
(122, 1, '19B', 19, 'B', '2026-11-21 18:11:38', 1),
(123, 1, '19C', 19, 'C', '2026-11-21 18:11:38', 1),
(124, 1, '19D', 19, 'D', '2026-11-21 18:11:38', 1),
(125, 1, '19E', 19, 'E', '2026-11-21 18:11:38', 1),
(126, 1, '19F', 19, 'F', '2026-11-21 18:11:38', 1),
(127, 1, '20A', 20, 'A', '2026-11-21 18:11:38', 1),
(128, 1, '20B', 20, 'B', '2026-11-21 18:11:38', 1),
(129, 1, '20C', 20, 'C', '2026-11-21 18:11:38', 1),
(130, 1, '20D', 20, 'D', '2026-11-21 18:11:38', 1),
(131, 1, '20E', 20, 'E', '2026-11-21 18:11:38', 1),
(132, 1, '20F', 20, 'F', '2026-11-21 18:11:38', 1),
(133, 1, '21A', 21, 'A', '2026-11-21 18:11:38', 1),
(134, 1, '21B', 21, 'B', '2026-11-21 18:11:38', 1),
(135, 1, '21C', 21, 'C', '2026-11-21 18:11:38', 1),
(136, 1, '21D', 21, 'D', '2026-11-21 18:11:38', 1),
(137, 1, '21E', 21, 'E', '2026-11-21 18:11:38', 1),
(138, 1, '21F', 21, 'F', '2026-11-21 18:11:38', 1),
(139, 1, '22A', 22, 'A', '2026-11-21 18:11:38', 1),
(140, 1, '22B', 22, 'B', '2026-11-21 18:11:38', 1),
(141, 1, '22C', 22, 'C', '2026-11-21 18:11:38', 1),
(142, 1, '22D', 22, 'D', '2026-11-21 18:11:38', 1),
(143, 1, '22E', 22, 'E', '2026-11-21 18:11:38', 1),
(144, 1, '22F', 22, 'F', '2026-11-21 18:11:38', 1),
(145, 1, '23A', 23, 'A', '2026-11-21 18:11:38', 1),
(146, 1, '23B', 23, 'B', '2026-11-21 18:11:38', 1),
(147, 1, '23C', 23, 'C', '2026-11-21 18:11:38', 1),
(148, 1, '23D', 23, 'D', '2026-11-21 18:11:38', 1),
(149, 1, '23E', 23, 'E', '2026-11-21 18:11:38', 1),
(150, 1, '23F', 23, 'F', '2026-11-21 18:11:38', 1),
(151, 1, '24A', 24, 'A', '2026-11-21 18:11:38', 1),
(152, 1, '24B', 24, 'B', '2026-11-21 18:11:38', 1),
(153, 1, '24C', 24, 'C', '2026-11-21 18:11:38', 1),
(154, 1, '24D', 24, 'D', '2026-11-21 18:11:38', 1),
(155, 1, '24E', 24, 'E', '2026-11-21 18:11:38', 1),
(156, 1, '24F', 24, 'F', '2026-11-21 18:11:38', 1),
(157, 1, '25A', 25, 'A', '2026-11-21 18:11:38', 1),
(158, 1, '25B', 25, 'B', '2026-11-21 18:11:38', 1),
(159, 1, '25C', 25, 'C', '2026-11-21 18:11:38', 1),
(160, 1, '25D', 25, 'D', '2026-11-21 18:11:38', 1),
(161, 1, '25E', 25, 'E', '2026-11-21 18:11:38', 1),
(162, 1, '25F', 25, 'F', '2026-11-21 18:11:38', 1),
(163, 1, '26A', 26, 'A', '2026-11-21 18:11:38', 1),
(164, 1, '26B', 26, 'B', '2026-11-21 18:11:38', 1),
(165, 1, '26C', 26, 'C', '2026-11-21 18:11:38', 1),
(166, 1, '26D', 26, 'D', '2026-11-21 18:11:38', 1),
(167, 1, '26E', 26, 'E', '2026-11-21 18:11:38', 1),
(168, 1, '26F', 26, 'F', '2026-11-21 18:11:38', 1),
(169, 1, '27A', 27, 'A', '2026-11-21 18:11:38', 1),
(170, 1, '27B', 27, 'B', '2026-11-21 18:11:38', 1),
(171, 1, '27C', 27, 'C', '2026-11-21 18:11:38', 1),
(172, 1, '27D', 27, 'D', '2026-11-21 18:11:38', 1),
(173, 1, '27E', 27, 'E', '2026-11-21 18:11:38', 1),
(174, 1, '27F', 27, 'F', '2026-11-21 18:11:38', 1),
(175, 1, '28A', 28, 'A', '2026-11-21 18:11:38', 1),
(176, 1, '28B', 28, 'B', '2026-11-21 18:11:38', 1),
(177, 1, '28C', 28, 'C', '2026-11-21 18:11:38', 1),
(178, 1, '28D', 28, 'D', '2026-11-21 18:11:38', 1),
(179, 1, '28E', 28, 'E', '2026-11-21 18:11:38', 1),
(180, 1, '28F', 28, 'F', '2026-11-21 18:11:38', 1),
(181, 1, '29A', 29, 'A', '2026-11-21 18:11:38', 1),
(182, 1, '29B', 29, 'B', '2026-11-21 18:11:38', 1),
(183, 1, '29C', 29, 'C', '2026-11-21 18:11:38', 1),
(184, 1, '29D', 29, 'D', '2026-11-21 18:11:38', 1),
(185, 1, '29E', 29, 'E', '2026-11-21 18:11:38', 1),
(186, 1, '29F', 29, 'F', '2026-11-21 18:11:38', 1),
(187, 1, '30A', 30, 'A', '2026-11-21 18:11:38', 1),
(188, 1, '30B', 30, 'B', '2026-11-21 18:11:38', 1),
(189, 1, '30C', 30, 'C', '2026-11-21 18:11:38', 1),
(190, 1, '30D', 30, 'D', '2026-11-21 18:11:38', 1),
(191, 1, '30E', 30, 'E', '2026-11-21 18:11:38', 1),
(192, 1, '30F', 30, 'F', '2026-11-21 18:11:38', 1),
(193, 2, '3A', 3, 'A', '2026-11-21 18:11:38', 1),
(194, 2, '3B', 3, 'B', '2026-11-21 18:11:38', 1),
(195, 2, '3C', 3, 'C', '2026-11-21 18:11:38', 1),
(196, 2, '3D', 3, 'D', '2026-11-21 18:11:38', 1),
(197, 2, '3E', 3, 'E', '2026-11-21 18:11:38', 1),
(198, 2, '3F', 3, 'F', '2026-11-21 18:11:38', 1),
(199, 2, '4A', 4, 'A', '2026-11-21 18:11:38', 1),
(200, 2, '4B', 4, 'B', '2026-11-21 18:11:38', 1),
(201, 2, '4C', 4, 'C', '2026-11-21 18:11:38', 1),
(202, 2, '4D', 4, 'D', '2026-11-21 18:11:38', 1),
(203, 2, '4E', 4, 'E', '2026-11-21 18:11:38', 1),
(204, 2, '4F', 4, 'F', '2026-11-21 18:11:38', 1),
(205, 2, '5A', 5, 'A', '2026-11-21 18:11:38', 1),
(206, 2, '5B', 5, 'B', '2026-11-21 18:11:38', 1),
(207, 2, '5C', 5, 'C', '2026-11-21 18:11:39', 1),
(208, 2, '5D', 5, 'D', '2026-11-21 18:11:39', 1),
(209, 2, '5E', 5, 'E', '2026-11-21 18:11:39', 1),
(210, 2, '5F', 5, 'F', '2026-11-21 18:11:39', 1),
(211, 2, '6A', 6, 'A', '2026-11-21 18:11:39', 1),
(212, 2, '6B', 6, 'B', '2026-11-21 18:11:39', 1),
(213, 2, '6C', 6, 'C', '2026-11-21 18:11:39', 1),
(214, 2, '6D', 6, 'D', '2026-11-21 18:11:39', 1),
(215, 2, '6E', 6, 'E', '2026-11-21 18:11:39', 1),
(216, 2, '6F', 6, 'F', '2026-11-21 18:11:39', 1),
(217, 2, '7A', 7, 'A', '2026-11-21 18:11:39', 1),
(218, 2, '7B', 7, 'B', '2026-11-21 18:11:39', 1),
(219, 2, '7C', 7, 'C', '2026-11-21 18:11:39', 1),
(220, 2, '7D', 7, 'D', '2026-11-21 18:11:39', 1),
(221, 2, '7E', 7, 'E', '2026-11-21 18:11:39', 1),
(222, 2, '7F', 7, 'F', '2026-11-21 18:11:39', 1),
(223, 2, '8A', 8, 'A', '2026-11-21 18:11:39', 1),
(224, 2, '8B', 8, 'B', '2026-11-21 18:11:39', 1),
(225, 2, '8C', 8, 'C', '2026-11-21 18:11:39', 1),
(226, 2, '8D', 8, 'D', '2026-11-21 18:11:39', 1),
(227, 2, '8E', 8, 'E', '2026-11-21 18:11:39', 1),
(228, 2, '8F', 8, 'F', '2026-11-21 18:11:39', 1),
(229, 2, '9A', 9, 'A', '2026-11-21 18:11:39', 1),
(230, 2, '9B', 9, 'B', '2026-11-21 18:11:39', 1),
(231, 2, '9C', 9, 'C', '2026-11-21 18:11:39', 1),
(232, 2, '9D', 9, 'D', '2026-11-21 18:11:39', 1),
(233, 2, '9E', 9, 'E', '2026-11-21 18:11:39', 1),
(234, 2, '9F', 9, 'F', '2026-11-21 18:11:39', 1),
(235, 2, '10A', 10, 'A', '2026-11-21 18:11:39', 1),
(236, 2, '10B', 10, 'B', '2026-11-21 18:11:39', 1),
(237, 2, '10C', 10, 'C', '2026-11-21 18:11:39', 1),
(238, 2, '10D', 10, 'D', '2026-11-21 18:11:39', 1),
(239, 2, '10E', 10, 'E', '2026-11-21 18:11:39', 1),
(240, 2, '10F', 10, 'F', '2026-11-21 18:11:39', 1),
(241, 2, '11A', 11, 'A', '2026-11-21 18:11:39', 1),
(242, 2, '11B', 11, 'B', '2026-11-21 18:11:39', 1),
(243, 2, '11C', 11, 'C', '2026-11-21 18:11:39', 1),
(244, 2, '11D', 11, 'D', '2026-11-21 18:11:39', 1),
(245, 2, '11E', 11, 'E', '2026-11-21 18:11:39', 1),
(246, 2, '11F', 11, 'F', '2026-11-21 18:11:39', 1),
(247, 2, '12A', 12, 'A', '2026-11-21 18:11:39', 1),
(248, 2, '12B', 12, 'B', '2026-11-21 18:11:39', 1),
(249, 2, '12C', 12, 'C', '2026-11-21 18:11:39', 1),
(250, 2, '12D', 12, 'D', '2026-11-21 18:11:39', 1),
(251, 2, '12E', 12, 'E', '2026-11-21 18:11:39', 1),
(252, 2, '12F', 12, 'F', '2026-11-21 18:11:39', 1),
(253, 2, '13A', 13, 'A', '2026-11-21 18:11:39', 1),
(254, 2, '13B', 13, 'B', '2026-11-21 18:11:39', 1),
(255, 2, '13C', 13, 'C', '2026-11-21 18:11:39', 1),
(256, 2, '13D', 13, 'D', '2026-11-21 18:11:39', 1),
(257, 2, '13E', 13, 'E', '2026-11-21 18:11:39', 1),
(258, 2, '13F', 13, 'F', '2026-11-21 18:11:39', 1),
(259, 2, '14A', 14, 'A', '2026-11-21 18:11:39', 1),
(260, 2, '14B', 14, 'B', '2026-11-21 18:11:39', 1),
(261, 2, '14C', 14, 'C', '2026-11-21 18:11:39', 1),
(262, 2, '14D', 14, 'D', '2026-11-21 18:11:39', 1),
(263, 2, '14E', 14, 'E', '2026-11-21 18:11:39', 1),
(264, 2, '14F', 14, 'F', '2026-11-21 18:11:39', 1),
(265, 2, '15A', 15, 'A', '2026-11-21 18:11:39', 1),
(266, 2, '15B', 15, 'B', '2026-11-21 18:11:39', 1),
(267, 2, '15C', 15, 'C', '2026-11-21 18:11:39', 1),
(268, 2, '15D', 15, 'D', '2026-11-21 18:11:39', 1),
(269, 2, '15E', 15, 'E', '2026-11-21 18:11:39', 1),
(270, 2, '15F', 15, 'F', '2026-11-21 18:11:39', 1),
(271, 2, '16A', 16, 'A', '2026-11-21 18:11:39', 1),
(272, 2, '16B', 16, 'B', '2026-11-21 18:11:39', 1),
(273, 2, '16C', 16, 'C', '2026-11-21 18:11:39', 1),
(274, 2, '16D', 16, 'D', '2026-11-21 18:11:39', 1),
(275, 2, '16E', 16, 'E', '2026-11-21 18:11:39', 1),
(276, 2, '16F', 16, 'F', '2026-11-21 18:11:39', 1),
(277, 2, '17A', 17, 'A', '2026-11-21 18:11:39', 1),
(278, 2, '17B', 17, 'B', '2026-11-21 18:11:39', 1),
(279, 2, '17C', 17, 'C', '2026-11-21 18:11:39', 1),
(280, 2, '17D', 17, 'D', '2026-11-21 18:11:39', 1),
(281, 2, '17E', 17, 'E', '2026-11-21 18:11:39', 1),
(282, 2, '17F', 17, 'F', '2026-11-21 18:11:39', 1),
(283, 2, '18A', 18, 'A', '2026-11-21 18:11:39', 1),
(284, 2, '18B', 18, 'B', '2026-11-21 18:11:39', 1),
(285, 2, '18C', 18, 'C', '2026-11-21 18:11:39', 1),
(286, 2, '18D', 18, 'D', '2026-11-21 18:11:39', 1),
(287, 2, '18E', 18, 'E', '2026-11-21 18:11:39', 1),
(288, 2, '18F', 18, 'F', '2026-11-21 18:11:39', 1),
(289, 2, '19A', 19, 'A', '2026-11-21 18:11:39', 1),
(290, 2, '19B', 19, 'B', '2026-11-21 18:11:39', 1),
(291, 2, '19C', 19, 'C', '2026-11-21 18:11:39', 1),
(292, 2, '19D', 19, 'D', '2026-11-21 18:11:39', 1),
(293, 2, '19E', 19, 'E', '2026-11-21 18:11:39', 1),
(294, 2, '19F', 19, 'F', '2026-11-21 18:11:39', 1),
(295, 2, '20A', 20, 'A', '2026-11-21 18:11:39', 1),
(296, 2, '20B', 20, 'B', '2026-11-21 18:11:39', 1),
(297, 2, '20C', 20, 'C', '2026-11-21 18:11:39', 1),
(298, 2, '20D', 20, 'D', '2026-11-21 18:11:39', 1),
(299, 2, '20E', 20, 'E', '2026-11-21 18:11:39', 1),
(300, 2, '20F', 20, 'F', '2026-11-21 18:11:39', 1),
(301, 2, '21A', 21, 'A', '2026-11-21 18:11:39', 1),
(302, 2, '21B', 21, 'B', '2026-11-21 18:11:39', 1),
(303, 2, '21C', 21, 'C', '2026-11-21 18:11:39', 1),
(304, 2, '21D', 21, 'D', '2026-11-21 18:11:39', 1),
(305, 2, '21E', 21, 'E', '2026-11-21 18:11:39', 1),
(306, 2, '21F', 21, 'F', '2026-11-21 18:11:39', 1),
(307, 2, '22A', 22, 'A', '2026-11-21 18:11:39', 1),
(308, 2, '22B', 22, 'B', '2026-11-21 18:11:39', 1),
(309, 2, '22C', 22, 'C', '2026-11-21 18:11:39', 1),
(310, 2, '22D', 22, 'D', '2026-11-21 18:11:39', 1),
(311, 2, '22E', 22, 'E', '2026-11-21 18:11:39', 1),
(312, 2, '22F', 22, 'F', '2026-11-21 18:11:39', 1),
(313, 2, '23A', 23, 'A', '2026-11-21 18:11:39', 1),
(314, 2, '23B', 23, 'B', '2026-11-21 18:11:39', 1),
(315, 2, '23C', 23, 'C', '2026-11-21 18:11:39', 1),
(316, 2, '23D', 23, 'D', '2026-11-21 18:11:39', 1),
(317, 2, '23E', 23, 'E', '2026-11-21 18:11:39', 1),
(318, 2, '23F', 23, 'F', '2026-11-21 18:11:39', 1),
(319, 2, '24A', 24, 'A', '2026-11-21 18:11:39', 1),
(320, 2, '24B', 24, 'B', '2026-11-21 18:11:39', 1),
(321, 2, '24C', 24, 'C', '2026-11-21 18:11:39', 1),
(322, 2, '24D', 24, 'D', '2026-11-21 18:11:39', 1),
(323, 2, '24E', 24, 'E', '2026-11-21 18:11:39', 1),
(324, 2, '24F', 24, 'F', '2026-11-21 18:11:39', 1),
(325, 2, '25A', 25, 'A', '2026-11-21 18:11:39', 1),
(326, 2, '25B', 25, 'B', '2026-11-21 18:11:39', 1),
(327, 2, '25C', 25, 'C', '2026-11-21 18:11:39', 1),
(328, 2, '25D', 25, 'D', '2026-11-21 18:11:39', 1),
(329, 2, '25E', 25, 'E', '2026-11-21 18:11:39', 1),
(330, 2, '25F', 25, 'F', '2026-11-21 18:11:39', 1),
(331, 2, '26A', 26, 'A', '2026-11-21 18:11:39', 1),
(332, 2, '26B', 26, 'B', '2026-11-21 18:11:39', 1),
(333, 2, '26C', 26, 'C', '2026-11-21 18:11:39', 1),
(334, 2, '26D', 26, 'D', '2026-11-21 18:11:39', 1),
(335, 2, '26E', 26, 'E', '2026-11-21 18:11:39', 1),
(336, 2, '26F', 26, 'F', '2026-11-21 18:11:39', 1),
(337, 2, '27A', 27, 'A', '2026-11-21 18:11:39', 1),
(338, 2, '27B', 27, 'B', '2026-11-21 18:11:39', 1),
(339, 2, '27C', 27, 'C', '2026-11-21 18:11:39', 1),
(340, 2, '27D', 27, 'D', '2026-11-21 18:11:39', 1),
(341, 2, '27E', 27, 'E', '2026-11-21 18:11:39', 1),
(342, 2, '27F', 27, 'F', '2026-11-21 18:11:39', 1),
(343, 2, '28A', 28, 'A', '2026-11-21 18:11:39', 1),
(344, 2, '28B', 28, 'B', '2026-11-21 18:11:39', 1),
(345, 2, '28C', 28, 'C', '2026-11-21 18:11:39', 1),
(346, 2, '28D', 28, 'D', '2026-11-21 18:11:39', 1),
(347, 2, '28E', 28, 'E', '2026-11-21 18:11:39', 1),
(348, 2, '28F', 28, 'F', '2026-11-21 18:11:39', 1),
(349, 2, '29A', 29, 'A', '2026-11-21 18:11:39', 1),
(350, 2, '29B', 29, 'B', '2026-11-21 18:11:39', 1),
(351, 2, '29C', 29, 'C', '2026-11-21 18:11:39', 1),
(352, 2, '29D', 29, 'D', '2026-11-21 18:11:39', 1),
(353, 2, '29E', 29, 'E', '2026-11-21 18:11:39', 1),
(354, 2, '29F', 29, 'F', '2026-11-21 18:11:39', 1),
(355, 2, '30A', 30, 'A', '2026-11-21 18:11:39', 1),
(356, 2, '30B', 30, 'B', '2026-11-21 18:11:39', 1),
(357, 2, '30C', 30, 'C', '2026-11-21 18:11:39', 1),
(358, 2, '30D', 30, 'D', '2026-11-21 18:11:39', 1),
(359, 2, '30E', 30, 'E', '2026-11-21 18:11:39', 1),
(360, 2, '30F', 30, 'F', '2026-11-21 18:11:39', 1),
(361, 2, '31A', 31, 'A', '2026-11-21 18:11:39', 1),
(362, 2, '31B', 31, 'B', '2026-11-21 18:11:39', 1),
(363, 2, '31C', 31, 'C', '2026-11-21 18:11:39', 1),
(364, 2, '31D', 31, 'D', '2026-11-21 18:11:39', 1),
(365, 2, '31E', 31, 'E', '2026-11-21 18:11:39', 1),
(366, 2, '31F', 31, 'F', '2026-11-21 18:11:39', 1),
(367, 2, '32A', 32, 'A', '2026-11-21 18:11:39', 1),
(368, 2, '32B', 32, 'B', '2026-11-21 18:11:39', 1),
(369, 2, '32C', 32, 'C', '2026-11-21 18:11:39', 1),
(370, 2, '32D', 32, 'D', '2026-11-21 18:11:39', 1),
(371, 2, '32E', 32, 'E', '2026-11-21 18:11:39', 1),
(372, 2, '32F', 32, 'F', '2026-11-21 18:11:39', 1),
(373, 3, '1A', 1, 'A', '2026-11-21 18:56:12', 1),
(374, 3, '1B', 1, 'B', '2026-11-21 18:56:12', 1),
(375, 3, '1C', 1, 'C', '2026-11-21 18:56:12', 1),
(376, 3, '2A', 2, 'A', '2026-11-21 18:56:12', 1),
(377, 3, '2B', 2, 'B', '2026-11-21 18:56:12', 1),
(378, 3, '2C', 2, 'C', '2026-11-21 18:56:12', 1),
(379, 3, '3A', 3, 'A', '2026-11-21 18:56:12', 1),
(380, 3, '3B', 3, 'B', '2026-11-21 18:56:12', 1),
(381, 3, '3C', 3, 'C', '2026-11-21 18:56:12', 1);

--
-- Acionadores `assento`
--
DELIMITER $$
CREATE TRIGGER `trg_assento_incrementar_versao` BEFORE UPDATE ON `assento` FOR EACH ROW SET NEW.versao = OLD.versao + 1
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `auditoria_tabela`
--

CREATE TABLE `auditoria_tabela` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tabela` varchar(50) NOT NULL,
  `registro_id` int(10) UNSIGNED NOT NULL,
  `usuario_cpf` char(11) DEFAULT NULL,
  `operacao` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `dados_antes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_antes`)),
  `dados_depois` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_depois`)),
  `data_hora` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `auditoria_tabela`
--

INSERT INTO `auditoria_tabela` (`id`, `tabela`, `registro_id`, `usuario_cpf`, `operacao`, `dados_antes`, `dados_depois`, `data_hora`) VALUES
(26, 'passagem', 21, '10005767601', 'UPDATE', '{\"status\": \"confirmada\", \"versao\": 1}', '{\"status\": \"cancelada\", \"versao\": 2}', '2026-11-21 17:42:13'),
(27, 'passagem', 20, '10005767601', 'UPDATE', '{\"status\": \"confirmada\", \"versao\": 1}', '{\"status\": \"cancelada\", \"versao\": 2}', '2026-11-21 17:49:38'),
(28, 'passagem', 20, '10005767601', 'UPDATE', '{\"status\": \"cancelada\", \"versao\": 2}', '{\"status\": \"confirmada\", \"versao\": 3}', '2026-11-21 17:49:38'),
(29, 'passagem', 17, '10005767601', 'UPDATE', '{\"status\": \"confirmada\", \"versao\": 1}', '{\"status\": \"cancelada\", \"versao\": 2}', '2026-11-21 17:50:31'),
(30, 'passagem', 20, '10005767601', 'UPDATE', '{\"status\": \"confirmada\", \"versao\": 3}', '{\"status\": \"cancelada\", \"versao\": 4}', '2026-11-21 17:52:01'),
(31, 'passagem', 19, '10005767601', 'UPDATE', '{\"status\": \"confirmada\", \"versao\": 1}', '{\"status\": \"cancelada\", \"versao\": 2}', '2026-11-21 17:52:15'),
(32, 'passagem', 27, '10005767601', 'INSERT', NULL, '{\"numero_bilhete\": \"267FFFEA21\", \"voo_id\": 5, \"assento_id\": 8, \"status\": \"confirmada\"}', '2026-11-21 17:58:06'),
(33, 'passagem', 28, '10005767601', 'INSERT', NULL, '{\"numero_bilhete\": \"3408AC1101\", \"voo_id\": 3, \"assento_id\": 12, \"status\": \"confirmada\"}', '2026-11-21 17:58:32'),
(34, 'passagem', 29, '10005767601', 'INSERT', NULL, '{\"numero_bilhete\": \"DB43805498\", \"voo_id\": 4, \"assento_id\": 24, \"status\": \"confirmada\"}', '2026-11-21 18:03:14'),
(36, 'passagem', 31, '10005767601', 'INSERT', NULL, '{\"numero_bilhete\": \"0FB4578CF1\", \"voo_id\": 4, \"assento_id\": 236, \"status\": \"confirmada\"}', '2026-11-21 18:14:37'),
(37, 'passagem', 32, '10005767601', 'INSERT', NULL, '{\"numero_bilhete\": \"5C855E6BA9\", \"voo_id\": 13, \"assento_id\": 223, \"status\": \"confirmada\"}', '2026-11-21 18:17:30'),
(38, 'passagem', 31, '10005767601', 'UPDATE', '{\"status\": \"confirmada\", \"versao\": 1}', '{\"status\": \"cancelada\", \"versao\": 2}', '2026-11-21 18:17:35');

-- --------------------------------------------------------

--
-- Estrutura para tabela `cliente`
--

CREATE TABLE `cliente` (
  `cpf` char(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `cliente`
--

INSERT INTO `cliente` (`cpf`) VALUES
('00000000001'),
('12345678903'),
('12345678904'),
('10005767601'),
('10005767604');

-- --------------------------------------------------------

--
-- Estrutura para tabela `escala`
--

CREATE TABLE `escala` (
  `id` int(10) UNSIGNED NOT NULL,
  `voo_id` int(10) UNSIGNED NOT NULL,
  `tripulante_cpf` char(11) NOT NULL,
  `funcao_tripulante` enum('piloto','copiloto','comissario') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `escala`
--

INSERT INTO `escala` (`id`, `voo_id`, `tripulante_cpf`, `funcao_tripulante`) VALUES
(1, 1, '12345678903', 'piloto'),
(2, 2, '00000000025', 'copiloto'),
(3, 3, '12345678903', 'piloto'),
(4, 4, '00000000025', 'copiloto'),
(5, 5, '10005767601', 'piloto'),
(6, 6, '00000000022', 'copiloto'),
(7, 7, '12345678903', 'piloto'),
(8, 8, '12345678903', 'piloto'),
(9, 9, '00000000023', 'copiloto'),
(10, 10, '00000000024', 'piloto'),
(11, 11, '12345678903', 'copiloto'),
(12, 12, '00000000025', 'piloto'),
(13, 13, '12345678903', 'copiloto'),
(14, 14, '10005767601', 'piloto'),
(15, 15, '00000000021', 'copiloto'),
(16, 16, '00000000024', 'piloto'),
(17, 17, '00000000023', 'copiloto'),
(18, 18, '12345678903', 'piloto'),
(19, 19, '10005767601', 'copiloto'),
(20, 20, '00000000022', 'piloto'),
(21, 21, '12345678903', 'copiloto'),
(22, 22, '12345678903', 'piloto'),
(23, 23, '12345678903', 'copiloto'),
(24, 24, '12345678903', 'piloto'),
(25, 25, '00000000021', 'copiloto'),
(26, 26, '10005767601', 'piloto'),
(27, 27, '00000000024', 'copiloto'),
(28, 28, '12345678903', 'piloto'),
(29, 29, '12345678903', 'copiloto'),
(30, 1, '00000000021', 'copiloto'),
(31, 2, '00000000022', 'piloto'),
(32, 4, '00000000023', 'piloto'),
(33, 1, '00000000026', 'comissario'),
(34, 2, '00000000027', 'comissario'),
(35, 3, '00000000028', 'comissario'),
(36, 4, '00000000029', 'comissario'),
(37, 3, '00000000024', 'copiloto'),
(39, 6, '00000000027', 'comissario'),
(40, 3, '00000000026', 'comissario');

--
-- Acionadores `escala`
--
DELIMITER $$
CREATE TRIGGER `trg_escala_validar_tripulante` BEFORE INSERT ON `escala` FOR EACH ROW BEGIN
    IF NOT EXISTS (SELECT 1 FROM tripulante WHERE cpf = NEW.tripulante_cpf) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Somente um tripulante cadastrado pode cumprir uma escala.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gerente`
--

CREATE TABLE `gerente` (
  `cpf` char(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `gerente`
--

INSERT INTO `gerente` (`cpf`) VALUES
('12345678902');

-- --------------------------------------------------------

--
-- Estrutura para tabela `log`
--

CREATE TABLE `log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_cpf` char(11) NOT NULL,
  `data_hora` timestamp NOT NULL DEFAULT current_timestamp(),
  `acao` varchar(80) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `registro_afetado` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `log`
--

INSERT INTO `log` (`id`, `usuario_cpf`, `data_hora`, `acao`, `descricao`, `registro_afetado`) VALUES
(1, '10005767601', '2026-11-21 16:25:45', 'CLIENTE_REGISTRADO', 'Novo cliente se registrou na plataforma.', 'usuario:10005767601'),
(2, '10005767601', '2026-11-21 16:25:57', 'LOGIN_CLIENTE', 'Cliente realizou login.', 'usuario:10005767601'),
(3, '10005767601', '2026-11-21 16:40:14', 'LOGIN_CLIENTE', 'Cliente realizou login.', 'usuario:10005767601'),
(4, '10005767604', '2026-11-21 16:52:34', 'CLIENTE_REGISTRADO', 'Novo cliente se registrou na plataforma.', 'usuario:10005767604'),
(5, '10005767604', '2026-11-21 16:52:47', 'LOGIN_CLIENTE', 'Cliente realizou login.', 'usuario:10005767604'),
(6, '12345678903', '2026-11-21 17:01:21', 'RESERVA_CRIADA', 'Passagem 7B412EA1D2 confirmada.', '7B412EA1D2'),
(7, '00000000001', '2026-11-21 17:04:12', 'LOGIN_CLIENTE', 'Cliente realizou login.', 'usuario:00000000001'),
(8, '00000000001', '2026-11-21 17:04:12', 'RESERVA_CRIADA', 'Passagem 828D3292FB confirmada.', '828D3292FB'),
(9, '00000000001', '2026-11-21 17:05:54', 'LOGIN_CLIENTE', 'Cliente realizou login.', 'usuario:00000000001'),
(10, '00000000001', '2026-11-21 17:07:38', 'LOGIN_CLIENTE', 'Cliente realizou login.', 'usuario:00000000001'),
(11, '00000000001', '2026-11-21 17:09:19', 'LOGIN_CLIENTE', 'Cliente realizou login.', 'usuario:00000000001'),
(12, '00000000001', '2026-11-21 17:10:41', 'RESERVA_CRIADA', 'Passagem FF918A31A5 confirmada.', 'FF918A31A5'),
(13, '00000000001', '2026-11-21 17:11:27', 'RESERVA_CRIADA', 'Passagem 9EF1F44DC7 confirmada.', '9EF1F44DC7'),
(14, '00000000001', '2026-11-21 17:11:43', 'RESERVA_CRIADA', 'Passagem 5F07AF3E1D confirmada.', '5F07AF3E1D'),
(15, '00000000001', '2026-11-21 17:12:45', 'RESERVA_CRIADA', 'Passagem 343FB4945E confirmada.', '343FB4945E'),
(16, '00000000001', '2026-11-21 17:13:00', 'RESERVA_CRIADA', 'Passagem E6BDC16DC4 confirmada.', 'E6BDC16DC4'),
(17, '00000000001', '2026-11-21 17:13:24', 'RESERVA_CRIADA', 'Passagem 7AA9ADB94A confirmada.', '7AA9ADB94A'),
(18, '00000000001', '2026-11-21 17:13:40', 'RESERVA_CRIADA', 'Passagem 0A27E3FC8C confirmada.', '0A27E3FC8C'),
(19, '00000000001', '2026-11-21 17:14:13', 'RESERVA_CRIADA', 'Passagem 3A7E822C5C confirmada.', '3A7E822C5C'),
(20, '00000000001', '2026-11-21 17:14:54', 'RESERVA_CRIADA', 'Passagem 44A7B3AB9B confirmada.', '44A7B3AB9B'),
(21, '10005767601', '2026-11-21 17:15:57', 'LOGIN_CLIENTE', 'Cliente realizou login.', 'usuario:10005767601'),
(22, '10005767601', '2026-11-21 17:16:11', 'LOGIN_CLIENTE', 'Cliente realizou login.', 'usuario:10005767601'),
(23, '10005767601', '2026-11-21 17:16:18', 'RESERVA_CRIADA', 'Passagem 2E458E162E confirmada.', '2E458E162E'),
(24, '10005767601', '2026-11-21 17:17:23', 'RESERVA_CRIADA', 'Passagem BCB3603F2E confirmada.', 'BCB3603F2E'),
(25, '10005767601', '2026-11-21 17:30:21', 'RESERVA_CRIADA', 'Passagem F55984882D confirmada.', 'F55984882D'),
(26, '10005767601', '2026-11-21 17:35:16', 'RESERVA_CRIADA', 'Passagem 6E7A5DE26A confirmada.', '6E7A5DE26A'),
(27, '10005767601', '2026-11-21 17:35:31', 'RESERVA_CRIADA', 'Passagem D53ED37BAA confirmada.', 'D53ED37BAA'),
(28, '10005767601', '2026-11-21 17:36:54', 'RESERVA_CRIADA', 'Passagem 8A37D07C9B confirmada.', '8A37D07C9B'),
(29, '10005767601', '2026-11-21 17:58:06', 'RESERVA_CRIADA', 'Passagem 267FFFEA21 confirmada.', '267FFFEA21'),
(30, '10005767601', '2026-11-21 17:58:32', 'RESERVA_CRIADA', 'Passagem 3408AC1101 confirmada.', '3408AC1101'),
(31, '10005767601', '2026-11-21 18:03:14', 'RESERVA_CRIADA', 'Passagem DB43805498 confirmada.', 'DB43805498'),
(33, '10005767601', '2026-11-21 18:14:37', 'RESERVA_CRIADA', 'Passagem 0FB4578CF1 confirmada.', '0FB4578CF1'),
(34, '10005767601', '2026-11-21 18:17:30', 'RESERVA_CRIADA', 'Passagem 5C855E6BA9 confirmada.', '5C855E6BA9'),
(35, '12345678901', '2026-11-21 18:23:37', 'LOGIN_ADMIN', 'Login realizado no perfil Administrador.', 'usuario:12345678901'),
(36, '12345678901', '2026-11-21 18:24:04', 'VOO_CRIADO', 'Voo DC8895 criado usando os horários do trajeto.', 'DC8895'),
(37, '12345678901', '2026-11-21 18:26:03', 'LOGIN_ADMIN', 'Login realizado no perfil Administrador.', 'usuario:12345678901'),
(38, '12345678901', '2026-11-21 18:39:37', 'ESCALA_CRIADA', 'Tripulante 00000000024 alocado ao voo G7-1101 como copiloto.', 'voo:3'),
(39, '12345678901', '2026-11-21 18:52:48', 'ESCALA_CRIADA', 'Tripulante 00000000027 alocado ao voo G7-1104 como comissario.', 'voo:6'),
(40, '12345678901', '2026-11-21 18:53:26', 'ESCALA_CRIADA', 'Tripulante 00000000026 alocado ao voo G7-1101 como comissario.', 'voo:3'),
(41, '12345678902', '2026-11-21 18:55:10', 'LOGIN_GERENTE', 'Login realizado no perfil Gerente.', 'usuario:12345678902'),
(42, '12345678902', '2026-11-21 18:56:12', 'AERONAVE_CRIADA', 'Aeronave PT-945 cadastrada.', 'aeronave:3'),
(43, '12345678902', '2026-11-21 18:56:25', 'TRIPULANTE_CRIADO', 'Função do tripulante definida como piloto.', 'tripulante:10005767601'),
(44, '12345678903', '2026-11-21 18:58:37', 'LOGIN_TRIPULANTE', 'Login realizado no perfil Tripulante.', 'usuario:12345678903');

-- --------------------------------------------------------

--
-- Estrutura para tabela `passagem`
--

CREATE TABLE `passagem` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `numero_bilhete` char(10) NOT NULL,
  `data_compra` timestamp NOT NULL DEFAULT current_timestamp(),
  `valor` decimal(10,2) NOT NULL,
  `status` enum('confirmada','cancelada','checkin') NOT NULL DEFAULT 'confirmada',
  `data_cancelamento` datetime DEFAULT NULL,
  `cliente_cpf` char(11) NOT NULL,
  `voo_id` int(10) UNSIGNED NOT NULL,
  `assento_id` int(10) UNSIGNED NOT NULL,
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `versao` int(10) UNSIGNED DEFAULT 1,
  `assento_ativo` tinyint(4) GENERATED ALWAYS AS (case when `status` in ('confirmada','checkin') then 1 else NULL end) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `passagem`
--

INSERT INTO `passagem` (`id`, `numero_bilhete`, `data_compra`, `valor`, `status`, `data_cancelamento`, `cliente_cpf`, `voo_id`, `assento_id`, `data_atualizacao`, `versao`) VALUES
(31, '0FB4578CF1', '2026-11-21 18:14:37', 329.90, 'cancelada', '2026-11-21 15:17:35', '10005767601', 4, 236, '2026-11-21 18:17:35', 2),
(32, '5C855E6BA9', '2026-11-21 18:17:30', 499.90, 'confirmada', NULL, '10005767601', 13, 223, '2026-11-21 18:17:30', 1);

--
-- Acionadores `passagem`
--
DELIMITER $$
CREATE TRIGGER `trg_auditoria_passagem_insert` AFTER INSERT ON `passagem` FOR EACH ROW INSERT INTO auditoria_tabela (tabela, registro_id, usuario_cpf, operacao, dados_depois)
VALUES ('passagem', NEW.id, NEW.cliente_cpf, 'INSERT', JSON_OBJECT('numero_bilhete', NEW.numero_bilhete, 'voo_id', NEW.voo_id, 'assento_id', NEW.assento_id, 'status', NEW.status))
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_auditoria_passagem_update` AFTER UPDATE ON `passagem` FOR EACH ROW INSERT INTO auditoria_tabela (tabela, registro_id, usuario_cpf, operacao, dados_antes, dados_depois)
VALUES ('passagem', NEW.id, NEW.cliente_cpf, 'UPDATE', JSON_OBJECT('status', OLD.status, 'versao', OLD.versao), JSON_OBJECT('status', NEW.status, 'versao', NEW.versao))
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_passagem_incrementar_versao` BEFORE UPDATE ON `passagem` FOR EACH ROW SET NEW.versao = OLD.versao + 1
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_passagem_validar_assento` BEFORE INSERT ON `passagem` FOR EACH ROW BEGIN
    DECLARE v_assento_aeronave INT UNSIGNED;
    DECLARE v_voo_aeronave INT UNSIGNED;
    SELECT aeronave_id INTO v_assento_aeronave FROM assento WHERE id = NEW.assento_id;
    SELECT aeronave_id INTO v_voo_aeronave FROM voo WHERE id = NEW.voo_id;
    IF v_assento_aeronave IS NULL OR v_voo_aeronave IS NULL OR v_assento_aeronave <> v_voo_aeronave THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O assento nao pertence a aeronave do voo.';
    END IF;
    IF NEW.valor < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O valor da passagem nao pode ser negativo.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `trajeto`
--

CREATE TABLE `trajeto` (
  `id` int(10) UNSIGNED NOT NULL,
  `origem` varchar(60) NOT NULL,
  `destino` varchar(60) NOT NULL,
  `h_partida` time NOT NULL,
  `h_chegada` time NOT NULL,
  `gerente_cpf` char(11) NOT NULL
) ;

--
-- Despejando dados para a tabela `trajeto`
--

INSERT INTO `trajeto` (`id`, `origem`, `destino`, `h_partida`, `h_chegada`, `gerente_cpf`) VALUES
(1, 'São Paulo (GRU)', 'Rio de Janeiro (GIG)', '08:00:00', '10:00:00', '12345678902'),
(2, 'Rio de Janeiro (GIG)', 'Brasília (BSB)', '09:00:00', '11:30:00', '12345678902'),
(3, 'São Paulo (GRU)', 'Brasília (BSB)', '10:30:00', '12:45:00', '12345678902'),
(4, 'Rio de Janeiro (GIG)', 'São Paulo (GRU)', '14:00:00', '15:45:00', '12345678902'),
(5, 'Brasília (BSB)', 'Rio de Janeiro (GIG)', '16:00:00', '18:15:00', '12345678902'),
(6, 'São Paulo (GRU)', 'São Luís (SLZ)', '06:00:00', '08:30:00', '12345678902'),
(7, 'São Luís (SLZ)', 'São Paulo (GRU)', '19:00:00', '21:30:00', '12345678902'),
(8, 'Recife (REC)', 'São Paulo (GRU)', '07:00:00', '09:30:00', '12345678902'),
(9, 'São Paulo (GRU)', 'Recife (REC)', '11:00:00', '13:30:00', '12345678902'),
(10, 'Brasília (BSB)', 'Belo Horizonte (CNF)', '09:00:00', '10:30:00', '12345678902'),
(11, 'Belo Horizonte (CNF)', 'São Paulo (GRU)', '15:30:00', '17:00:00', '12345678902'),
(12, 'São Paulo (GRU)', 'Brasília (BSB)', '10:30:00', '12:45:00', '12345678902'),
(13, 'Rio de Janeiro (GIG)', 'São Paulo (GRU)', '14:00:00', '15:45:00', '12345678902'),
(14, 'Brasília (BSB)', 'Rio de Janeiro (GIG)', '16:00:00', '18:15:00', '12345678902'),
(15, 'São Paulo (GRU)', 'São Luís (SLZ)', '06:00:00', '08:30:00', '12345678902'),
(16, 'São Luís (SLZ)', 'São Paulo (GRU)', '19:00:00', '21:30:00', '12345678902'),
(17, 'Recife (REC)', 'São Paulo (GRU)', '07:00:00', '09:30:00', '12345678902'),
(18, 'São Paulo (GRU)', 'Recife (REC)', '11:00:00', '13:30:00', '12345678902'),
(19, 'Brasília (BSB)', 'Belo Horizonte (CNF)', '09:00:00', '10:30:00', '12345678902'),
(20, 'Belo Horizonte (CNF)', 'São Paulo (GRU)', '15:30:00', '17:00:00', '12345678902');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tripulante`
--

CREATE TABLE `tripulante` (
  `cpf` char(11) NOT NULL,
  `funcao` enum('piloto','copiloto','comissario') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `tripulante`
--

INSERT INTO `tripulante` (`cpf`, `funcao`) VALUES
('00000000021', 'piloto'),
('00000000022', 'piloto'),
('00000000023', 'piloto'),
('00000000024', 'piloto'),
('00000000025', 'piloto'),
('00000000026', 'comissario'),
('00000000027', 'comissario'),
('00000000028', 'comissario'),
('00000000029', 'comissario'),
('00000000030', 'comissario'),
('00000000031', 'comissario'),
('10005767601', 'piloto'),
('12345678903', 'piloto');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario`
--

CREATE TABLE `usuario` (
  `cpf` char(11) NOT NULL,
  `nome_completo` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `endereco` varchar(255) NOT NULL,
  `telefone` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuario`
--

INSERT INTO `usuario` (`cpf`, `nome_completo`, `email`, `senha`, `endereco`, `telefone`) VALUES
('00000000001', 'Cliente Teste', 'cliente@teste.com', '$2y$12$8O/arIZ77quJX8X1U7CVoeQo4gKM/dmd4TeaB.jTRJc6d/eemt09W', 'Rua Teste, 123', NULL),
('00000000021', 'Leonardo Rocha', 'piloto.01@decolae.com', '$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm', 'Rua sebastião 13, bairro 22 de outubro', NULL),
('00000000022', 'Sebastião Pedro', 'piloto.demo02@decolae.com', '$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm', 'Rua sebastião 13, bairro 22 de outubro', NULL),
('00000000023', 'Neymar Junior', 'piloto.3@decolae.com', '$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm', 'Endereço de demonstração', NULL),
('00000000024', 'Vinicius Junior', 'piloto.4@decolae.com', '$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm', 'Endereço de demonstração', NULL),
('00000000025', 'Pedro Arrascaeta', 'piloto.5@decolae.com', '$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm', 'Endereço de demonstração', NULL),
('00000000026', 'Rossi Golger', 'comissario.demo01@decolae.com', '$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm', 'Endereço de demonstração', NULL),
('00000000027', 'Luiz Guilherme', 'comissario.demo02@decolae.com', '$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm', 'Endereço de demonstração', NULL),
('00000000028', 'Nivaldo Andrade', 'comissario.demo03@decolae.com', '$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm', 'Endereço de demonstração', NULL),
('00000000029', 'Luiz Inácio', 'comissario.demo04@decolae.com', '$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm', 'Endereço de demonstração', NULL),
('00000000030', 'Virginia Fonseca', 'comissario.demo05@decolae.com', '$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm', 'Endereço de demonstração', NULL),
('00000000031', 'Paulo Fernando Neves', 'comissario.6@decolae.com', '$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm', 'Endereço de demonstração', NULL),
('10005767601', 'ricardo marcio mamede', 'ricardo@ricardo.com', '$2y$12$DelASUVgy42JSXLMSbidy.tt6XexVIaWAcShmf7MklTM9ZlZADRke', '', '32998312505'),
('10005767604', 'paulo', 'paulo@paulao.com', '$2y$12$/PCsH01j/13jqZoKLBdrGe5VaMIC3PWxUK7M3bpXQM0dsEbvTwtMq', '', '32998312508'),
('12345678901', 'Admin Silva', 'admin@decolae.com', '$2y$12$4qo2Zxs6rSKCeHsEsexuOem6tqePndmb6f03R96YZATXZprPL2FkS', 'Rua Principal 100', NULL),
('12345678902', 'Gerente Costa', 'gerente@decolae.com', '$2y$12$2nIObKXtWcLOLMt64XD8SeB7igA2GbRFYefvFilKMSEo5./mdTU1q', 'Avenida Central 200', NULL),
('12345678903', 'Tripulante Santos', 'tripulante@decolae.com', '$2y$12$yKIYmhiMJlHbPd8i5Q7rPuZPGaoJVFP1YjmvoHhqdfRWoOpzbiyue', 'Rua Lateral 300', NULL),
('12345678904', 'Cliente Oliveira', 'cliente@decolae.com', '$2y$12$d2AoEViwhM0L4K8BAZx1A.nz1rlwe1pnhiE0sTPt58zZ1UE5x1y2C', 'Rua Cliente 400', '11999999999');

-- --------------------------------------------------------

--
-- Estrutura para tabela `voo`
--

CREATE TABLE `voo` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero_voo` varchar(12) NOT NULL,
  `data_voo` date NOT NULL,
  `trajeto_id` int(10) UNSIGNED NOT NULL,
  `aeronave_id` int(10) UNSIGNED NOT NULL,
  `admin_cpf` char(11) NOT NULL,
  `valor_base` decimal(10,2) NOT NULL DEFAULT 649.90,
  `status` enum('programado','embarque','concluido','cancelado') NOT NULL DEFAULT 'programado',
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `versao` int(10) UNSIGNED DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `voo`
--

INSERT INTO `voo` (`id`, `numero_voo`, `data_voo`, `trajeto_id`, `aeronave_id`, `admin_cpf`, `valor_base`, `status`, `data_atualizacao`, `versao`) VALUES
(1, 'G7-1001', '2026-11-29', 1, 1, '12345678901', 299.90, 'programado', '2026-11-21 16:27:20', 2),
(2, 'G7-1002', '2026-11-29', 2, 2, '12345678901', 349.90, 'programado', '2026-11-21 16:27:26', 2),
(3, 'G7-1101', '2026-11-25', 1, 1, '12345678901', 299.90, 'programado', '2026-11-21 16:29:29', 1),
(4, 'G7-1102', '2026-11-25', 1, 2, '12345678901', 329.90, 'programado', '2026-11-21 16:29:29', 1),
(5, 'G7-1103', '2026-11-25', 1, 1, '12345678901', 299.90, 'programado', '2026-11-21 16:29:29', 1),
(6, 'G7-1104', '2026-11-25', 2, 2, '12345678901', 349.90, 'programado', '2026-11-21 16:29:29', 1),
(7, 'G7-1105', '2026-11-25', 3, 1, '12345678901', 379.90, 'programado', '2026-11-21 16:29:29', 1),
(8, 'G7-1201', '2026-11-26', 2, 1, '12345678901', 299.90, 'programado', '2026-11-21 16:29:29', 1),
(9, 'G7-1202', '2026-11-26', 2, 2, '12345678901', 329.90, 'programado', '2026-11-21 16:29:29', 1),
(10, 'G7-1203', '2026-11-26', 3, 1, '12345678901', 349.90, 'programado', '2026-11-21 16:29:29', 1),
(11, 'G7-1204', '2026-11-26', 4, 2, '12345678901', 449.90, 'programado', '2026-11-21 16:29:29', 1),
(12, 'G7-1301', '2026-11-27', 5, 1, '12345678901', 449.90, 'programado', '2026-11-21 16:29:29', 1),
(13, 'G7-1302', '2026-11-27', 6, 2, '12345678901', 499.90, 'programado', '2026-11-21 16:29:29', 1),
(14, 'G7-1303', '2026-11-27', 7, 1, '12345678901', 499.90, 'programado', '2026-11-21 16:29:29', 1),
(15, 'G7-1401', '2026-11-28', 8, 2, '12345678901', 199.90, 'programado', '2026-11-21 16:29:29', 1),
(16, 'G7-1402', '2026-11-28', 9, 1, '12345678901', 379.90, 'programado', '2026-11-21 16:29:29', 1),
(17, 'G7-1501', '2026-11-29', 2, 2, '12345678901', 299.90, 'programado', '2026-11-21 16:29:29', 1),
(18, 'G7-2101', '2026-12-01', 1, 1, '12345678901', 299.90, 'programado', '2026-11-21 16:29:29', 1),
(19, 'G7-2102', '2026-12-01', 1, 2, '12345678901', 329.90, 'programado', '2026-11-21 16:29:29', 1),
(20, 'G7-2103', '2026-12-01', 2, 1, '12345678901', 349.90, 'programado', '2026-11-21 16:29:29', 1),
(21, 'G7-2201', '2026-12-02', 4, 2, '12345678901', 449.90, 'programado', '2026-11-21 16:29:29', 1),
(22, 'G7-2202', '2026-12-02', 5, 1, '12345678901', 449.90, 'programado', '2026-11-21 16:29:29', 1),
(23, 'G7-2301', '2026-12-03', 6, 2, '12345678901', 499.90, 'programado', '2026-11-21 16:29:29', 1),
(24, 'G7-2302', '2026-12-03', 7, 1, '12345678901', 499.90, 'programado', '2026-11-21 16:29:29', 1),
(25, 'G7-2401', '2026-12-04', 8, 2, '12345678901', 199.90, 'programado', '2026-11-21 16:29:29', 1),
(26, 'G7-2402', '2026-12-04', 9, 1, '12345678901', 379.90, 'programado', '2026-11-21 16:29:29', 1),
(27, 'G7-2501', '2026-12-05', 2, 2, '12345678901', 299.90, 'programado', '2026-11-21 16:29:29', 1),
(28, 'G7-2601', '2026-12-06', 1, 1, '12345678901', 299.90, 'programado', '2026-11-21 16:29:29', 1),
(29, 'G7-2701', '2026-12-07', 2, 2, '12345678901', 349.90, 'programado', '2026-11-21 16:29:29', 1),
(45, 'DC8895', '2026-11-25', 20, 1, '12345678901', 649.90, 'programado', '2026-11-21 18:24:04', 1);

--
-- Acionadores `voo`
--
DELIMITER $$
CREATE TRIGGER `trg_voo_incrementar_versao` BEFORE UPDATE ON `voo` FOR EACH ROW SET NEW.versao = OLD.versao + 1
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_voos_disponibilidade`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_voos_disponibilidade` (
`id` int(10) unsigned
,`numero_voo` varchar(12)
,`data_voo` date
,`status` enum('programado','embarque','concluido','cancelado')
,`valor_base` decimal(10,2)
,`origem` varchar(60)
,`destino` varchar(60)
,`h_partida` time
,`h_chegada` time
,`partida_em` varchar(21)
,`chegada_em` varchar(21)
,`modelo` varchar(80)
,`identificacao` varchar(20)
,`capacidade` int(8) unsigned
,`assentos_livres` bigint(21) unsigned
);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`cpf`);

--
-- Índices de tabela `aeronave`
--
ALTER TABLE `aeronave`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `identificacao` (`identificacao`),
  ADD KEY `fk_aeronave_gerente` (`gerente_cpf`),
  ADD KEY `idx_aeronave_versao` (`id`,`versao`);

--
-- Índices de tabela `assento`
--
ALTER TABLE `assento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_assento_aeronave` (`aeronave_id`,`codigo_assento`),
  ADD KEY `idx_assento_versao` (`id`,`versao`);

--
-- Índices de tabela `auditoria_tabela`
--
ALTER TABLE `auditoria_tabela`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auditoria_tabela_registro` (`tabela`,`registro_id`),
  ADD KEY `idx_auditoria_data` (`data_hora`),
  ADD KEY `fk_auditoria_usuario` (`usuario_cpf`);

--
-- Índices de tabela `cliente`
--
ALTER TABLE `cliente`
  ADD PRIMARY KEY (`cpf`);

--
-- Índices de tabela `escala`
--
ALTER TABLE `escala`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_escala_voo_tripulante` (`voo_id`,`tripulante_cpf`),
  ADD KEY `idx_escala_tripulante` (`tripulante_cpf`);

--
-- Índices de tabela `gerente`
--
ALTER TABLE `gerente`
  ADD PRIMARY KEY (`cpf`);

--
-- Índices de tabela `log`
--
ALTER TABLE `log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_log_data` (`data_hora`),
  ADD KEY `idx_log_usuario` (`usuario_cpf`);

--
-- Índices de tabela `passagem`
--
ALTER TABLE `passagem`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_bilhete` (`numero_bilhete`),
  ADD UNIQUE KEY `uq_passagem_assento_voo` (`voo_id`,`assento_id`,`assento_ativo`),
  ADD KEY `fk_passagem_assento` (`assento_id`),
  ADD KEY `idx_passagem_voo_status` (`voo_id`,`status`),
  ADD KEY `idx_passagem_cliente` (`cliente_cpf`),
  ADD KEY `idx_passagem_versao` (`id`,`versao`);

--
-- Índices de tabela `trajeto`
--
ALTER TABLE `trajeto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_trajeto_gerente` (`gerente_cpf`),
  ADD KEY `idx_trajeto_rota` (`origem`,`destino`);

--
-- Índices de tabela `tripulante`
--
ALTER TABLE `tripulante`
  ADD PRIMARY KEY (`cpf`);

--
-- Índices de tabela `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`cpf`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `telefone` (`telefone`);

--
-- Índices de tabela `voo`
--
ALTER TABLE `voo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_voo_numero_data` (`numero_voo`,`data_voo`),
  ADD KEY `fk_voo_trajeto` (`trajeto_id`),
  ADD KEY `fk_voo_admin` (`admin_cpf`),
  ADD KEY `idx_voo_busca` (`data_voo`,`status`,`trajeto_id`),
  ADD KEY `idx_voo_aeronave_data` (`aeronave_id`,`data_voo`),
  ADD KEY `idx_voo_versao` (`id`,`versao`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `aeronave`
--
ALTER TABLE `aeronave`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `assento`
--
ALTER TABLE `assento`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=382;

--
-- AUTO_INCREMENT de tabela `auditoria_tabela`
--
ALTER TABLE `auditoria_tabela`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT de tabela `escala`
--
ALTER TABLE `escala`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT de tabela `log`
--
ALTER TABLE `log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT de tabela `passagem`
--
ALTER TABLE `passagem`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT de tabela `trajeto`
--
ALTER TABLE `trajeto`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `voo`
--
ALTER TABLE `voo`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_voos_disponibilidade`
--
DROP TABLE IF EXISTS `vw_voos_disponibilidade`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_voos_disponibilidade`  AS SELECT `v`.`id` AS `id`, `v`.`numero_voo` AS `numero_voo`, `v`.`data_voo` AS `data_voo`, `v`.`status` AS `status`, `v`.`valor_base` AS `valor_base`, `t`.`origem` AS `origem`, `t`.`destino` AS `destino`, `t`.`h_partida` AS `h_partida`, `t`.`h_chegada` AS `h_chegada`, concat(`v`.`data_voo`,' ',`t`.`h_partida`) AS `partida_em`, concat(`v`.`data_voo`,' ',`t`.`h_chegada`) AS `chegada_em`, `a`.`modelo` AS `modelo`, `a`.`identificacao` AS `identificacao`, `a`.`fileiras`* `a`.`assentos_por_fileira` AS `capacidade`, coalesce(`a`.`fileiras` * `a`.`assentos_por_fileira` - (select count(0) from `passagem` where `passagem`.`voo_id` = `v`.`id` and `passagem`.`status` in ('confirmada','checkin')),`a`.`fileiras` * `a`.`assentos_por_fileira`) AS `assentos_livres` FROM ((`voo` `v` join `trajeto` `t` on(`t`.`id` = `v`.`trajeto_id`)) join `aeronave` `a` on(`a`.`id` = `v`.`aeronave_id`)) ;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `fk_admin_usuario` FOREIGN KEY (`cpf`) REFERENCES `usuario` (`cpf`) ON DELETE CASCADE;

--
-- Restrições para tabelas `aeronave`
--
ALTER TABLE `aeronave`
  ADD CONSTRAINT `fk_aeronave_gerente` FOREIGN KEY (`gerente_cpf`) REFERENCES `gerente` (`cpf`);

--
-- Restrições para tabelas `assento`
--
ALTER TABLE `assento`
  ADD CONSTRAINT `fk_assento_aeronave` FOREIGN KEY (`aeronave_id`) REFERENCES `aeronave` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `auditoria_tabela`
--
ALTER TABLE `auditoria_tabela`
  ADD CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_cpf`) REFERENCES `usuario` (`cpf`) ON DELETE SET NULL;

--
-- Restrições para tabelas `cliente`
--
ALTER TABLE `cliente`
  ADD CONSTRAINT `fk_cliente_usuario` FOREIGN KEY (`cpf`) REFERENCES `usuario` (`cpf`) ON DELETE CASCADE;

--
-- Restrições para tabelas `escala`
--
ALTER TABLE `escala`
  ADD CONSTRAINT `fk_escala_tripulante` FOREIGN KEY (`tripulante_cpf`) REFERENCES `tripulante` (`cpf`),
  ADD CONSTRAINT `fk_escala_voo` FOREIGN KEY (`voo_id`) REFERENCES `voo` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `gerente`
--
ALTER TABLE `gerente`
  ADD CONSTRAINT `fk_gerente_usuario` FOREIGN KEY (`cpf`) REFERENCES `usuario` (`cpf`) ON DELETE CASCADE;

--
-- Restrições para tabelas `log`
--
ALTER TABLE `log`
  ADD CONSTRAINT `fk_log_usuario` FOREIGN KEY (`usuario_cpf`) REFERENCES `usuario` (`cpf`);

--
-- Restrições para tabelas `passagem`
--
ALTER TABLE `passagem`
  ADD CONSTRAINT `fk_passagem_assento` FOREIGN KEY (`assento_id`) REFERENCES `assento` (`id`),
  ADD CONSTRAINT `fk_passagem_cliente` FOREIGN KEY (`cliente_cpf`) REFERENCES `cliente` (`cpf`),
  ADD CONSTRAINT `fk_passagem_voo` FOREIGN KEY (`voo_id`) REFERENCES `voo` (`id`);

--
-- Restrições para tabelas `trajeto`
--
ALTER TABLE `trajeto`
  ADD CONSTRAINT `fk_trajeto_gerente` FOREIGN KEY (`gerente_cpf`) REFERENCES `gerente` (`cpf`);

--
-- Restrições para tabelas `tripulante`
--
ALTER TABLE `tripulante`
  ADD CONSTRAINT `fk_tripulante_usuario` FOREIGN KEY (`cpf`) REFERENCES `usuario` (`cpf`) ON DELETE CASCADE;

--
-- Restrições para tabelas `voo`
--
ALTER TABLE `voo`
  ADD CONSTRAINT `fk_voo_admin` FOREIGN KEY (`admin_cpf`) REFERENCES `admin` (`cpf`),
  ADD CONSTRAINT `fk_voo_aeronave` FOREIGN KEY (`aeronave_id`) REFERENCES `aeronave` (`id`),
  ADD CONSTRAINT `fk_voo_trajeto` FOREIGN KEY (`trajeto_id`) REFERENCES `trajeto` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
