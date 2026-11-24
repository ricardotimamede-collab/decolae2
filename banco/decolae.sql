

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `aeronave`
--

INSERT INTO `aeronave` (`id`, `identificacao`, `modelo`, `fileiras`, `assentos_por_fileira`, `gerente_cpf`, `data_atualizacao`, `versao`) VALUES
(2,'PT-DEF','Airbus A320',32,4,'12345678902','2026-11-24 16:46:51',2),
(8,'ABC-123','Boeing 125',6,6,'12345678902','2026-11-23 15:15:45',1);

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
(390,8,'1A',1,'A','2026-11-23 15:15:45',1),
(391,8,'1B',1,'B','2026-11-23 15:15:45',1),
(392,8,'1C',1,'C','2026-11-23 15:15:45',1),
(393,8,'1D',1,'D','2026-11-23 15:15:45',1),
(394,8,'1E',1,'E','2026-11-23 15:15:45',1),
(395,8,'1F',1,'F','2026-11-23 15:15:45',1),
(396,8,'2A',2,'A','2026-11-23 15:15:45',1),
(397,8,'2B',2,'B','2026-11-23 15:15:45',1),
(398,8,'2C',2,'C','2026-11-23 15:15:45',1),
(399,8,'2D',2,'D','2026-11-23 15:15:45',1),
(400,8,'2E',2,'E','2026-11-23 15:15:45',1),
(401,8,'2F',2,'F','2026-11-23 15:15:45',1),
(402,8,'3A',3,'A','2026-11-23 15:15:45',1),
(403,8,'3B',3,'B','2026-11-23 15:15:45',1),
(404,8,'3C',3,'C','2026-11-23 15:15:45',1),
(405,8,'3D',3,'D','2026-11-23 15:15:45',1),
(406,8,'3E',3,'E','2026-11-23 15:15:45',1),
(407,8,'3F',3,'F','2026-11-23 15:15:45',1),
(408,8,'4A',4,'A','2026-11-23 15:15:45',1),
(409,8,'4B',4,'B','2026-11-23 15:15:45',1),
(410,8,'4C',4,'C','2026-11-23 15:15:45',1),
(411,8,'4D',4,'D','2026-11-23 15:15:45',1),
(412,8,'4E',4,'E','2026-11-23 15:15:45',1),
(413,8,'4F',4,'F','2026-11-23 15:15:45',1),
(414,8,'5A',5,'A','2026-11-23 15:15:45',1),
(415,8,'5B',5,'B','2026-11-23 15:15:45',1),
(416,8,'5C',5,'C','2026-11-23 15:15:45',1),
(417,8,'5D',5,'D','2026-11-23 15:15:45',1),
(418,8,'5E',5,'E','2026-11-23 15:15:45',1),
(419,8,'5F',5,'F','2026-11-23 15:15:45',1),
(420,8,'6A',6,'A','2026-11-23 15:15:45',1),
(421,8,'6B',6,'B','2026-11-23 15:15:45',1),
(422,8,'6C',6,'C','2026-11-23 15:15:45',1),
(423,8,'6D',6,'D','2026-11-23 15:15:45',1),
(424,8,'6E',6,'E','2026-11-23 15:15:45',1),
(425,8,'6F',6,'F','2026-11-23 15:15:45',1),
(532,2,'1A',1,'A','2026-11-24 16:46:51',1),
(533,2,'1B',1,'B','2026-11-24 16:46:51',1),
(534,2,'1C',1,'C','2026-11-24 16:46:51',1),
(535,2,'1D',1,'D','2026-11-24 16:46:51',1),
(536,2,'2A',2,'A','2026-11-24 16:46:51',1),
(537,2,'2B',2,'B','2026-11-24 16:46:51',1),
(538,2,'2C',2,'C','2026-11-24 16:46:51',1),
(539,2,'2D',2,'D','2026-11-24 16:46:51',1),
(540,2,'3A',3,'A','2026-11-24 16:46:51',1),
(541,2,'3B',3,'B','2026-11-24 16:46:51',1),
(542,2,'3C',3,'C','2026-11-24 16:46:51',1),
(543,2,'3D',3,'D','2026-11-24 16:46:51',1),
(544,2,'4A',4,'A','2026-11-24 16:46:51',1),
(545,2,'4B',4,'B','2026-11-24 16:46:51',1),
(546,2,'4C',4,'C','2026-11-24 16:46:51',1),
(547,2,'4D',4,'D','2026-11-24 16:46:51',1),
(548,2,'5A',5,'A','2026-11-24 16:46:51',1),
(549,2,'5B',5,'B','2026-11-24 16:46:51',1),
(550,2,'5C',5,'C','2026-11-24 16:46:51',1),
(551,2,'5D',5,'D','2026-11-24 16:46:51',1),
(552,2,'6A',6,'A','2026-11-24 16:46:51',1),
(553,2,'6B',6,'B','2026-11-24 16:46:51',1),
(554,2,'6C',6,'C','2026-11-24 16:46:51',1),
(555,2,'6D',6,'D','2026-11-24 16:46:51',1),
(556,2,'7A',7,'A','2026-11-24 16:46:51',1),
(557,2,'7B',7,'B','2026-11-24 16:46:51',1),
(558,2,'7C',7,'C','2026-11-24 16:46:51',1),
(559,2,'7D',7,'D','2026-11-24 16:46:51',1),
(560,2,'8A',8,'A','2026-11-24 16:46:51',1),
(561,2,'8B',8,'B','2026-11-24 16:46:51',1),
(562,2,'8C',8,'C','2026-11-24 16:46:51',1),
(563,2,'8D',8,'D','2026-11-24 16:46:51',1),
(564,2,'9A',9,'A','2026-11-24 16:46:51',1),
(565,2,'9B',9,'B','2026-11-24 16:46:51',1),
(566,2,'9C',9,'C','2026-11-24 16:46:51',1),
(567,2,'9D',9,'D','2026-11-24 16:46:51',1),
(568,2,'10A',10,'A','2026-11-24 16:46:51',1),
(569,2,'10B',10,'B','2026-11-24 16:46:51',1),
(570,2,'10C',10,'C','2026-11-24 16:46:51',1),
(571,2,'10D',10,'D','2026-11-24 16:46:51',1),
(572,2,'11A',11,'A','2026-11-24 16:46:51',1),
(573,2,'11B',11,'B','2026-11-24 16:46:51',1),
(574,2,'11C',11,'C','2026-11-24 16:46:51',1),
(575,2,'11D',11,'D','2026-11-24 16:46:51',1),
(576,2,'12A',12,'A','2026-11-24 16:46:51',1),
(577,2,'12B',12,'B','2026-11-24 16:46:51',1),
(578,2,'12C',12,'C','2026-11-24 16:46:51',1),
(579,2,'12D',12,'D','2026-11-24 16:46:51',1),
(580,2,'13A',13,'A','2026-11-24 16:46:51',1),
(581,2,'13B',13,'B','2026-11-24 16:46:51',1),
(582,2,'13C',13,'C','2026-11-24 16:46:51',1),
(583,2,'13D',13,'D','2026-11-24 16:46:51',1),
(584,2,'14A',14,'A','2026-11-24 16:46:51',1),
(585,2,'14B',14,'B','2026-11-24 16:46:51',1),
(586,2,'14C',14,'C','2026-11-24 16:46:51',1),
(587,2,'14D',14,'D','2026-11-24 16:46:51',1),
(588,2,'15A',15,'A','2026-11-24 16:46:51',1),
(589,2,'15B',15,'B','2026-11-24 16:46:51',1),
(590,2,'15C',15,'C','2026-11-24 16:46:51',1),
(591,2,'15D',15,'D','2026-11-24 16:46:51',1),
(592,2,'16A',16,'A','2026-11-24 16:46:51',1),
(593,2,'16B',16,'B','2026-11-24 16:46:51',1),
(594,2,'16C',16,'C','2026-11-24 16:46:51',1),
(595,2,'16D',16,'D','2026-11-24 16:46:51',1),
(596,2,'17A',17,'A','2026-11-24 16:46:51',1),
(597,2,'17B',17,'B','2026-11-24 16:46:51',1),
(598,2,'17C',17,'C','2026-11-24 16:46:51',1),
(599,2,'17D',17,'D','2026-11-24 16:46:51',1),
(600,2,'18A',18,'A','2026-11-24 16:46:51',1),
(601,2,'18B',18,'B','2026-11-24 16:46:51',1),
(602,2,'18C',18,'C','2026-11-24 16:46:51',1),
(603,2,'18D',18,'D','2026-11-24 16:46:51',1),
(604,2,'19A',19,'A','2026-11-24 16:46:51',1),
(605,2,'19B',19,'B','2026-11-24 16:46:51',1),
(606,2,'19C',19,'C','2026-11-24 16:46:51',1),
(607,2,'19D',19,'D','2026-11-24 16:46:51',1),
(608,2,'20A',20,'A','2026-11-24 16:46:51',1),
(609,2,'20B',20,'B','2026-11-24 16:46:51',1),
(610,2,'20C',20,'C','2026-11-24 16:46:51',1),
(611,2,'20D',20,'D','2026-11-24 16:46:51',1),
(612,2,'21A',21,'A','2026-11-24 16:46:51',1),
(613,2,'21B',21,'B','2026-11-24 16:46:51',1),
(614,2,'21C',21,'C','2026-11-24 16:46:51',1),
(615,2,'21D',21,'D','2026-11-24 16:46:51',1),
(616,2,'22A',22,'A','2026-11-24 16:46:51',1),
(617,2,'22B',22,'B','2026-11-24 16:46:51',1),
(618,2,'22C',22,'C','2026-11-24 16:46:51',1),
(619,2,'22D',22,'D','2026-11-24 16:46:51',1),
(620,2,'23A',23,'A','2026-11-24 16:46:51',1),
(621,2,'23B',23,'B','2026-11-24 16:46:51',1),
(622,2,'23C',23,'C','2026-11-24 16:46:51',1),
(623,2,'23D',23,'D','2026-11-24 16:46:51',1),
(624,2,'24A',24,'A','2026-11-24 16:46:51',1),
(625,2,'24B',24,'B','2026-11-24 16:46:51',1),
(626,2,'24C',24,'C','2026-11-24 16:46:51',1),
(627,2,'24D',24,'D','2026-11-24 16:46:51',1),
(628,2,'25A',25,'A','2026-11-24 16:46:51',1),
(629,2,'25B',25,'B','2026-11-24 16:46:51',1),
(630,2,'25C',25,'C','2026-11-24 16:46:51',1),
(631,2,'25D',25,'D','2026-11-24 16:46:51',1),
(632,2,'26A',26,'A','2026-11-24 16:46:51',1),
(633,2,'26B',26,'B','2026-11-24 16:46:51',1),
(634,2,'26C',26,'C','2026-11-24 16:46:51',1),
(635,2,'26D',26,'D','2026-11-24 16:46:51',1),
(636,2,'27A',27,'A','2026-11-24 16:46:51',1),
(637,2,'27B',27,'B','2026-11-24 16:46:51',1),
(638,2,'27C',27,'C','2026-11-24 16:46:51',1),
(639,2,'27D',27,'D','2026-11-24 16:46:51',1),
(640,2,'28A',28,'A','2026-11-24 16:46:51',1),
(641,2,'28B',28,'B','2026-11-24 16:46:51',1),
(642,2,'28C',28,'C','2026-11-24 16:46:51',1),
(643,2,'28D',28,'D','2026-11-24 16:46:51',1),
(644,2,'29A',29,'A','2026-11-24 16:46:51',1),
(645,2,'29B',29,'B','2026-11-24 16:46:51',1),
(646,2,'29C',29,'C','2026-11-24 16:46:51',1),
(647,2,'29D',29,'D','2026-11-24 16:46:51',1),
(648,2,'30A',30,'A','2026-11-24 16:46:51',1),
(649,2,'30B',30,'B','2026-11-24 16:46:51',1),
(650,2,'30C',30,'C','2026-11-24 16:46:51',1),
(651,2,'30D',30,'D','2026-11-24 16:46:51',1),
(652,2,'31A',31,'A','2026-11-24 16:46:51',1),
(653,2,'31B',31,'B','2026-11-24 16:46:51',1),
(654,2,'31C',31,'C','2026-11-24 16:46:51',1),
(655,2,'31D',31,'D','2026-11-24 16:46:51',1),
(656,2,'32A',32,'A','2026-11-24 16:46:51',1),
(657,2,'32B',32,'B','2026-11-24 16:46:51',1),
(658,2,'32C',32,'C','2026-11-24 16:46:51',1),
(659,2,'32D',32,'D','2026-11-24 16:46:51',1);

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
(26,'passagem',21,'10005767601','UPDATE','{\"status\": \"confirmada\", \"versao\": 1}','{\"status\": \"cancelada\", \"versao\": 2}','2026-11-21 17:42:13'),
(27,'passagem',20,'10005767601','UPDATE','{\"status\": \"confirmada\", \"versao\": 1}','{\"status\": \"cancelada\", \"versao\": 2}','2026-11-21 17:49:38'),
(28,'passagem',20,'10005767601','UPDATE','{\"status\": \"cancelada\", \"versao\": 2}','{\"status\": \"confirmada\", \"versao\": 3}','2026-11-21 17:49:38'),
(29,'passagem',17,'10005767601','UPDATE','{\"status\": \"confirmada\", \"versao\": 1}','{\"status\": \"cancelada\", \"versao\": 2}','2026-11-21 17:50:31'),
(30,'passagem',20,'10005767601','UPDATE','{\"status\": \"confirmada\", \"versao\": 3}','{\"status\": \"cancelada\", \"versao\": 4}','2026-11-21 17:52:01'),
(31,'passagem',19,'10005767601','UPDATE','{\"status\": \"confirmada\", \"versao\": 1}','{\"status\": \"cancelada\", \"versao\": 2}','2026-11-21 17:52:15'),
(32,'passagem',27,'10005767601','INSERT',NULL,'{\"numero_bilhete\": \"267FFFEA21\", \"voo_id\": 5, \"assento_id\": 8, \"status\": \"confirmada\"}','2026-11-21 17:58:06'),
(33,'passagem',28,'10005767601','INSERT',NULL,'{\"numero_bilhete\": \"3408AC1101\", \"voo_id\": 3, \"assento_id\": 12, \"status\": \"confirmada\"}','2026-11-21 17:58:32'),
(34,'passagem',29,'10005767601','INSERT',NULL,'{\"numero_bilhete\": \"DB43805498\", \"voo_id\": 4, \"assento_id\": 24, \"status\": \"confirmada\"}','2026-11-21 18:03:14'),
(36,'passagem',31,'10005767601','INSERT',NULL,'{\"numero_bilhete\": \"0FB4578CF1\", \"voo_id\": 4, \"assento_id\": 236, \"status\": \"confirmada\"}','2026-11-21 18:14:37'),
(37,'passagem',32,'10005767601','INSERT',NULL,'{\"numero_bilhete\": \"5C855E6BA9\", \"voo_id\": 13, \"assento_id\": 223, \"status\": \"confirmada\"}','2026-11-21 18:17:30'),
(38,'passagem',31,'10005767601','UPDATE','{\"status\": \"confirmada\", \"versao\": 1}','{\"status\": \"cancelada\", \"versao\": 2}','2026-11-21 18:17:35'),
(39,'passagem',33,'14434473616','INSERT',NULL,'{\"numero_bilhete\": \"600373F0DF\", \"voo_id\": 3, \"assento_id\": 2, \"status\": \"confirmada\"}','2026-11-23 14:03:11'),
(40,'passagem',34,'14434473616','INSERT',NULL,'{\"numero_bilhete\": \"1C5843E56E\", \"voo_id\": 3, \"assento_id\": 31, \"status\": \"confirmada\"}','2026-11-23 14:03:29'),
(41,'passagem',34,'14434473616','UPDATE','{\"status\": \"confirmada\", \"versao\": 1}','{\"status\": \"cancelada\", \"versao\": 2}','2026-11-23 14:13:24'),
(42,'passagem',35,'12345678904','INSERT',NULL,'{\"numero_bilhete\": \"D75D619FA4\", \"voo_id\": 3, \"assento_id\": 5, \"status\": \"confirmada\"}','2026-11-23 14:36:50'),
(43,'passagem',36,'12345678904','INSERT',NULL,'{\"numero_bilhete\": \"EF367C7165\", \"voo_id\": 45, \"assento_id\": 5, \"status\": \"confirmada\"}','2026-11-23 14:36:50'),
(44,'passagem',35,'12345678904','UPDATE','{\"status\": \"confirmada\", \"versao\": 1}','{\"status\": \"cancelada\", \"versao\": 2}','2026-11-23 14:36:58'),
(45,'passagem',37,'12345678904','INSERT',NULL,'{\"numero_bilhete\": \"26644A7B2F\", \"voo_id\": 3, \"assento_id\": 5, \"status\": \"confirmada\"}','2026-11-23 14:36:58'),
(46,'passagem',38,'12345678904','INSERT',NULL,'{\"numero_bilhete\": \"AD042509A1\", \"voo_id\": 3, \"assento_id\": 40, \"status\": \"confirmada\"}','2026-11-23 19:54:26'),
(47,'passagem',39,'12345678904','INSERT',NULL,'{\"numero_bilhete\": \"DC3CF67625\", \"voo_id\": 3, \"assento_id\": 40, \"status\": \"confirmada\"}','2026-11-23 19:54:35'),
(48,'passagem',40,'12345678904','INSERT',NULL,'{\"numero_bilhete\": \"376F94F061\", \"voo_id\": 45, \"assento_id\": 71, \"status\": \"confirmada\"}','2026-11-23 19:56:02'),
(49,'passagem',41,'12345678904','INSERT',NULL,'{\"numero_bilhete\": \"A95BF79F09\", \"voo_id\": 3, \"assento_id\": 40, \"status\": \"confirmada\"}','2026-11-23 20:02:20'),
(50,'passagem',42,'14434473616','INSERT',NULL,'{\"numero_bilhete\": \"3EF90580F2\", \"voo_id\": 51, \"assento_id\": 408, \"status\": \"confirmada\"}','2026-11-23 20:03:47'),
(51,'passagem',43,'12345678904','INSERT',NULL,'{\"numero_bilhete\": \"B8DB67F10E\", \"voo_id\": 67, \"assento_id\": 424, \"status\": \"confirmada\"}','2026-11-24 16:09:09'),
(52,'passagem',44,'12345678904','INSERT',NULL,'{\"numero_bilhete\": \"9186B0762F\", \"voo_id\": 67, \"assento_id\": 420, \"status\": \"confirmada\"}','2026-11-24 16:44:56'),
(53,'passagem',43,'12345678904','UPDATE','{\"status\": \"confirmada\", \"versao\": 1}','{\"status\": \"cancelada\", \"versao\": 2}','2026-11-24 16:45:04');

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
('00000000021'),
('00000000022'),
('10005767601'),
('10005767604'),
('12345678903'),
('12345678904'),
('14434473616'),
('14434573616'),
('14785236945');

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
(76,67,'00000000024','piloto'),
(77,67,'00000000025','copiloto'),
(78,67,'00000000028','comissario'),
(79,67,'00000000029','comissario'),
(84,75,'00000000022','piloto'),
(85,75,'00000000023','copiloto'),
(86,75,'00000000026','comissario'),
(87,75,'00000000027','comissario'),
(88,76,'00000000022','piloto'),
(89,76,'00000000023','copiloto'),
(90,76,'00000000026','comissario'),
(91,76,'00000000027','comissario'),
(92,68,'00000000024','piloto'),
(93,68,'00000000025','copiloto'),
(94,68,'00000000028','comissario'),
(95,68,'00000000029','comissario'),
(96,77,'00000000022','piloto'),
(97,77,'00000000023','copiloto'),
(98,77,'00000000026','comissario'),
(99,77,'00000000027','comissario'),
(100,78,'00000000024','piloto'),
(101,78,'00000000025','copiloto'),
(102,78,'00000000028','comissario'),
(103,78,'00000000029','comissario'),
(104,79,'00000000024','piloto'),
(105,79,'00000000025','copiloto'),
(106,79,'00000000028','comissario'),
(107,79,'00000000029','comissario'),
(108,80,'12345678903','piloto'),
(109,80,'00000000022','copiloto'),
(110,80,'00000000030','comissario'),
(111,80,'00000000031','comissario'),
(112,81,'00000000024','piloto'),
(113,81,'00000000025','copiloto'),
(114,81,'00000000028','comissario'),
(115,81,'00000000029','comissario'),
(116,82,'12345678903','piloto'),
(117,82,'00000000022','copiloto'),
(118,82,'00000000030','comissario'),
(119,82,'00000000031','comissario'),
(120,83,'12345678903','piloto'),
(121,83,'00000000022','copiloto'),
(122,83,'00000000030','comissario'),
(123,83,'00000000031','comissario'),
(124,84,'12345678903','piloto'),
(125,84,'00000000022','copiloto'),
(126,84,'00000000030','comissario'),
(127,84,'00000000031','comissario');

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
(1,'10005767601','2026-11-21 16:25:45','CLIENTE_REGISTRADO','Novo cliente se registrou na plataforma.','usuario:10005767601'),
(2,'10005767601','2026-11-21 16:25:57','LOGIN_CLIENTE','Cliente realizou login.','usuario:10005767601'),
(3,'10005767601','2026-11-21 16:40:14','LOGIN_CLIENTE','Cliente realizou login.','usuario:10005767601'),
(4,'10005767604','2026-11-21 16:52:34','CLIENTE_REGISTRADO','Novo cliente se registrou na plataforma.','usuario:10005767604'),
(5,'10005767604','2026-11-21 16:52:47','LOGIN_CLIENTE','Cliente realizou login.','usuario:10005767604'),
(6,'12345678903','2026-11-21 17:01:21','RESERVA_CRIADA','Passagem 7B412EA1D2 confirmada.','7B412EA1D2'),
(7,'00000000001','2026-11-21 17:04:12','LOGIN_CLIENTE','Cliente realizou login.','usuario:00000000001'),
(8,'00000000001','2026-11-21 17:04:12','RESERVA_CRIADA','Passagem 828D3292FB confirmada.','828D3292FB'),
(9,'00000000001','2026-11-21 17:05:54','LOGIN_CLIENTE','Cliente realizou login.','usuario:00000000001'),
(10,'00000000001','2026-11-21 17:07:38','LOGIN_CLIENTE','Cliente realizou login.','usuario:00000000001'),
(11,'00000000001','2026-11-21 17:09:19','LOGIN_CLIENTE','Cliente realizou login.','usuario:00000000001'),
(12,'00000000001','2026-11-21 17:10:41','RESERVA_CRIADA','Passagem FF918A31A5 confirmada.','FF918A31A5'),
(13,'00000000001','2026-11-21 17:11:27','RESERVA_CRIADA','Passagem 9EF1F44DC7 confirmada.','9EF1F44DC7'),
(14,'00000000001','2026-11-21 17:11:43','RESERVA_CRIADA','Passagem 5F07AF3E1D confirmada.','5F07AF3E1D'),
(15,'00000000001','2026-11-21 17:12:45','RESERVA_CRIADA','Passagem 343FB4945E confirmada.','343FB4945E'),
(16,'00000000001','2026-11-21 17:13:00','RESERVA_CRIADA','Passagem E6BDC16DC4 confirmada.','E6BDC16DC4'),
(17,'00000000001','2026-11-21 17:13:24','RESERVA_CRIADA','Passagem 7AA9ADB94A confirmada.','7AA9ADB94A'),
(18,'00000000001','2026-11-21 17:13:40','RESERVA_CRIADA','Passagem 0A27E3FC8C confirmada.','0A27E3FC8C'),
(19,'00000000001','2026-11-21 17:14:13','RESERVA_CRIADA','Passagem 3A7E822C5C confirmada.','3A7E822C5C'),
(20,'00000000001','2026-11-21 17:14:54','RESERVA_CRIADA','Passagem 44A7B3AB9B confirmada.','44A7B3AB9B'),
(21,'10005767601','2026-11-21 17:15:57','LOGIN_CLIENTE','Cliente realizou login.','usuario:10005767601'),
(22,'10005767601','2026-11-21 17:16:11','LOGIN_CLIENTE','Cliente realizou login.','usuario:10005767601'),
(23,'10005767601','2026-11-21 17:16:18','RESERVA_CRIADA','Passagem 2E458E162E confirmada.','2E458E162E'),
(24,'10005767601','2026-11-21 17:17:23','RESERVA_CRIADA','Passagem BCB3603F2E confirmada.','BCB3603F2E'),
(25,'10005767601','2026-11-21 17:30:21','RESERVA_CRIADA','Passagem F55984882D confirmada.','F55984882D'),
(26,'10005767601','2026-11-21 17:35:16','RESERVA_CRIADA','Passagem 6E7A5DE26A confirmada.','6E7A5DE26A'),
(27,'10005767601','2026-11-21 17:35:31','RESERVA_CRIADA','Passagem D53ED37BAA confirmada.','D53ED37BAA'),
(28,'10005767601','2026-11-21 17:36:54','RESERVA_CRIADA','Passagem 8A37D07C9B confirmada.','8A37D07C9B'),
(29,'10005767601','2026-11-21 17:58:06','RESERVA_CRIADA','Passagem 267FFFEA21 confirmada.','267FFFEA21'),
(30,'10005767601','2026-11-21 17:58:32','RESERVA_CRIADA','Passagem 3408AC1101 confirmada.','3408AC1101'),
(31,'10005767601','2026-11-21 18:03:14','RESERVA_CRIADA','Passagem DB43805498 confirmada.','DB43805498'),
(33,'10005767601','2026-11-21 18:14:37','RESERVA_CRIADA','Passagem 0FB4578CF1 confirmada.','0FB4578CF1'),
(34,'10005767601','2026-11-21 18:17:30','RESERVA_CRIADA','Passagem 5C855E6BA9 confirmada.','5C855E6BA9'),
(35,'12345678901','2026-11-21 18:23:37','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(36,'12345678901','2026-11-21 18:24:04','VOO_CRIADO','Voo DC8895 criado usando os horários do trajeto.','DC8895'),
(37,'12345678901','2026-11-21 18:26:03','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(38,'12345678901','2026-11-21 18:39:37','ESCALA_CRIADA','Tripulante 00000000024 alocado ao voo G7-1101 como copiloto.','voo:3'),
(39,'12345678901','2026-11-21 18:52:48','ESCALA_CRIADA','Tripulante 00000000027 alocado ao voo G7-1104 como comissario.','voo:6'),
(40,'12345678901','2026-11-21 18:53:26','ESCALA_CRIADA','Tripulante 00000000026 alocado ao voo G7-1101 como comissario.','voo:3'),
(41,'12345678902','2026-11-21 18:55:10','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(42,'12345678902','2026-11-21 18:56:12','AERONAVE_CRIADA','Aeronave PT-945 cadastrada.','aeronave:3'),
(43,'12345678902','2026-11-21 18:56:25','TRIPULANTE_CRIADO','Função do tripulante definida como piloto.','tripulante:10005767601'),
(44,'12345678903','2026-11-21 18:58:37','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:12345678903'),
(45,'14434473616','2026-11-23 13:56:31','CLIENTE_REGISTRADO','Novo cliente se registrou na plataforma.','usuario:14434473616'),
(46,'14434473616','2026-11-23 13:56:47','LOGIN_CLIENTE','Cliente realizou login.','usuario:14434473616'),
(47,'14434473616','2026-11-23 14:03:11','RESERVA_CRIADA','Passagem 600373F0DF confirmada.','600373F0DF'),
(48,'14434473616','2026-11-23 14:03:29','RESERVA_CRIADA','Passagem 1C5843E56E confirmada.','1C5843E56E'),
(49,'14434473616','2026-11-23 14:15:41','LOGIN_CLIENTE','Cliente realizou login.','usuario:14434473616'),
(50,'12345678903','2026-11-23 14:26:29','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678903'),
(51,'12345678904','2026-11-23 14:36:20','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(52,'12345678904','2026-11-23 14:36:28','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(56,'12345678903','2026-11-23 14:40:21','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:12345678903'),
(57,'12345678903','2026-11-23 14:41:52','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:12345678903'),
(67,'12345678902','2026-11-23 15:15:45','AERONAVE_CRIADA','Aeronave ABC-123 cadastrada.','aeronave:8'),
(68,'12345678901','2026-11-23 15:20:41','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(71,'12345678901','2026-11-23 15:29:18','VOO_CRIADO','Voo 123456 de 2026-11-26 criado usando os horários do trajeto.','voo:51'),
(72,'12345678901','2026-11-23 15:29:33','VOO_CRIADO','Voo 987DA de 2026-11-26 criado usando os horários do trajeto.','voo:52'),
(73,'12345678901','2026-11-23 15:31:09','ESCALA_CRIADA','Tripulante 00000000021 alocado ao voo 123456 como copiloto.','voo:51'),
(76,'00000000021','2026-11-23 16:50:37','PERFIL_CLIENTE_ATIVADO','Usuário existente ativou o perfil de cliente.','usuario:00000000021'),
(80,'14434573616','2026-11-23 17:01:10','CLIENTE_REGISTRADO','Novo cliente se registrou na plataforma.','usuario:14434573616'),
(81,'14434573616','2026-11-23 17:01:23','LOGIN_CLIENTE','Cliente realizou login.','usuario:14434573616'),
(82,'00000000022','2026-11-23 17:04:33','PERFIL_CLIENTE_ATIVADO','Usuário existente ativou o perfil de cliente.','usuario:00000000022'),
(83,'12345678904','2026-11-23 17:28:19','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(84,'12345678904','2026-11-23 17:28:58','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(88,'12345678904','2026-11-23 19:18:15','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(89,'12345678904','2026-11-23 19:25:20','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(90,'12345678904','2026-11-23 19:43:53','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(94,'12345678904','2026-11-23 19:55:26','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(95,'12345678904','2026-11-23 19:56:02','RESERVA_CRIADA','Passagem 376F94F061 confirmada.','376F94F061'),
(98,'14434473616','2026-11-23 20:03:20','LOGIN_CLIENTE','Cliente realizou login.','usuario:14434473616'),
(99,'14434473616','2026-11-23 20:03:47','RESERVA_CRIADA','Passagem 3EF90580F2 confirmada.','3EF90580F2'),
(100,'12345678903','2026-11-23 20:28:27','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:12345678903'),
(101,'00000000021','2026-11-23 20:28:28','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:00000000021'),
(102,'00000000022','2026-11-23 20:28:28','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:00000000022'),
(103,'00000000021','2026-11-23 20:32:04','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:00000000021'),
(104,'00000000021','2026-11-23 20:33:17','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:00000000021'),
(114,'12345678901','2026-11-24 01:38:04','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(115,'12345678901','2026-11-24 01:40:08','ESCALA_CRIADA','Tripulante 00000000030 alocado ao voo G7-1101 como comissario.','voo:3'),
(116,'12345678901','2026-11-24 01:41:21','ESCALA_CRIADA','Tripulante 00000000030 alocado ao voo G7-1105 como comissario.','voo:7'),
(117,'12345678901','2026-11-24 01:41:38','ESCALA_CRIADA','Tripulante 00000000030 alocado ao voo DC8895 como comissario.','voo:45'),
(118,'12345678901','2026-11-24 01:41:51','ESCALA_CRIADA','Tripulante 00000000030 alocado ao voo 987DA como comissario.','voo:52'),
(119,'12345678901','2026-11-24 01:42:09','ESCALA_CRIADA','Tripulante 00000000030 alocado ao voo G7-1204 como comissario.','voo:11'),
(120,'12345678903','2026-11-24 13:30:39','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678903'),
(121,'12345678903','2026-11-24 13:30:42','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:12345678903'),
(122,'00000000021','2026-11-24 13:30:43','LOGIN_CLIENTE','Cliente realizou login.','usuario:00000000021'),
(123,'00000000021','2026-11-24 13:30:44','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:00000000021'),
(131,'12345678901','2026-11-24 14:25:18','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(132,'12345678901','2026-11-24 14:26:12','VOO_CRIADO','Voo TESTE12 de 2026-12-03 criado usando os horários do trajeto.','voo:59'),
(133,'12345678901','2026-11-24 14:28:10','VOO_CRIADO','Voo HKJKJ de 2026-12-04 criado usando os horários do trajeto.','voo:60'),
(134,'12345678904','2026-11-24 14:34:55','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(135,'00000000021','2026-11-24 14:34:56','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:00000000021'),
(136,'12345678901','2026-11-24 14:34:57','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(137,'12345678902','2026-11-24 14:34:57','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(145,'12345678901','2026-11-24 14:43:26','VOO_CRIADO','Voo QWE de 2026-11-27 criado usando os horários do trajeto.','voo:67'),
(146,'12345678901','2026-11-24 14:44:05','VOO_CRIADO','Voo JKL de 2026-11-29 criado usando os horários do trajeto.','voo:68'),
(147,'12345678902','2026-11-24 14:53:03','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(151,'12345678902','2026-11-24 14:54:54','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(152,'12345678904','2026-11-24 15:04:04','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(153,'00000000021','2026-11-24 15:04:05','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:00000000021'),
(154,'12345678901','2026-11-24 15:04:06','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(162,'12345678902','2026-11-24 15:12:06','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(165,'12345678902','2026-11-24 15:14:06','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(166,'12345678902','2026-11-24 15:14:09','TRAJETO_ALTERADO','Trajeto Belo Horizonte (CNF) → São Paulo (GRU) alterado.','trajeto:20'),
(167,'12345678902','2026-11-24 15:14:24','TRAJETO_ALTERADO','Trajeto Belo Horizonte (CNF) → São Paulo (GRU) alterado.','trajeto:20'),
(168,'12345678902','2026-11-24 15:14:41','TRAJETO_ALTERADO','Trajeto Belo Horizonte (CNF) → São Paulo (GRU) alterado.','trajeto:20'),
(169,'12345678902','2026-11-24 15:14:46','TRAJETO_EXCLUIDO','Trajeto Belo Horizonte (CNF) → São Paulo (GRU) excluído.','trajeto:20'),
(170,'12345678902','2026-11-24 15:14:59','TRAJETO_EXCLUIDO','Trajeto Brasília (BSB) → Belo Horizonte (CNF) excluído.','trajeto:10'),
(175,'12345678902','2026-11-24 15:15:52','TRAJETO_CRIADO','Trajeto Brasília (BSB) → São Paulo (GRU) cadastrado.','trajeto:40'),
(176,'12345678902','2026-11-24 15:16:04','TRAJETO_ALTERADO','Trajeto Brasília (BSB) → São Paulo (GRU) alterado.','trajeto:40'),
(177,'12345678902','2026-11-24 15:16:17','TRAJETO_EXCLUIDO','Trajeto Brasília (BSB) → São Paulo (GRU) excluído.','trajeto:40'),
(181,'12345678902','2026-11-24 15:19:00','TRAJETO_EXCLUIDO','Trajeto Recife (REC) → São Paulo (GRU) excluído.','trajeto:8'),
(182,'12345678902','2026-11-24 15:19:07','TRAJETO_EXCLUIDO','Trajeto Brasília (BSB) → Rio de Janeiro (GIG) excluído.','trajeto:5'),
(183,'12345678902','2026-11-24 15:20:06','TRAJETO_EXCLUIDO','Trajeto Brasília (BSB) → Rio de Janeiro (GIG) excluído.','trajeto:14'),
(184,'12345678902','2026-11-24 15:20:21','TRAJETO_EXCLUIDO','Trajeto Recife (REC) → São Paulo (GRU) excluído.','trajeto:17'),
(185,'12345678902','2026-11-24 15:22:51','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(186,'12345678902','2026-11-24 15:24:36','AERONAVE_EXCLUIDA','Aeronave PT-945 excluída.','aeronave:3'),
(187,'12345678902','2026-11-24 15:24:47','AERONAVE_ALTERADA','Aeronave PT-ABC alterada.','aeronave:1'),
(188,'12345678902','2026-11-24 15:24:59','AERONAVE_CRIADA','Aeronave TESTE12 cadastrada.','aeronave:11'),
(189,'12345678902','2026-11-24 15:25:08','AERONAVE_EXCLUIDA','Aeronave TESTE12 excluída.','aeronave:11'),
(190,'12345678902','2026-11-24 15:27:15','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(191,'12345678901','2026-11-24 15:27:16','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(192,'12345678902','2026-11-24 15:32:47','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(193,'12345678902','2026-11-24 15:40:57','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(194,'12345678901','2026-11-24 15:41:59','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(195,'12345678902','2026-11-24 15:47:48','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(196,'12345678902','2026-11-24 15:52:02','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(197,'12345678902','2026-11-24 15:54:00','TRIPULANTE_CRIADO','Tripulante Jão Pedro Santana cadastrado como piloto.','tripulante:14785236945'),
(198,'14785236945','2026-11-24 15:55:29','PERFIL_CLIENTE_ATIVADO','Usuário existente ativou o perfil de cliente.','usuario:14785236945'),
(199,'14785236945','2026-11-24 15:55:40','LOGIN_CLIENTE','Cliente realizou login.','usuario:14785236945'),
(200,'12345678902','2026-11-24 15:56:21','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(201,'12345678902','2026-11-24 15:58:10','TRIPULANTE_EXCLUIDO','Tripulante Jão Pedro Santana excluído.','tripulante:14785236945'),
(202,'12345678902','2026-11-24 15:58:19','TRIPULANTE_EXCLUIDO','Tripulante Leonardo Rocha excluído.','tripulante:00000000021'),
(203,'12345678902','2026-11-24 16:00:12','TRIPULANTE_EXCLUIDO','Tripulante ricardo marcio mamede excluído.','tripulante:10005767601'),
(204,'12345678902','2026-11-24 16:01:37','TRIPULANTE_PERFIL_ADICIONADO','Perfil de tripulante adicionado à conta de Cliente Teste.','tripulante:00000000001'),
(205,'12345678902','2026-11-24 16:01:42','TRIPULANTE_EXCLUIDO','Tripulante Cliente Teste excluído.','tripulante:00000000001'),
(206,'12345678904','2026-11-24 16:02:53','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(207,'12345678902','2026-11-24 16:02:58','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(208,'12345678903','2026-11-24 16:03:01','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:12345678903'),
(209,'12345678904','2026-11-24 16:05:01','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(210,'12345678902','2026-11-24 16:05:06','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(211,'12345678904','2026-11-24 16:08:51','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(212,'12345678904','2026-11-24 16:09:09','RESERVA_CRIADA','Passagem B8DB67F10E confirmada.','B8DB67F10E'),
(213,'12345678904','2026-11-24 16:44:26','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(214,'12345678904','2026-11-24 16:44:56','RESERVA_CRIADA','Passagem 9186B0762F confirmada.','9186B0762F'),
(215,'12345678902','2026-11-24 16:45:26','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(216,'12345678902','2026-11-24 16:45:36','TRAJETO_EXCLUIDO','Trajeto Rio de Janeiro (GIG) → Brasília (BSB) excluído.','trajeto:2'),
(217,'12345678902','2026-11-24 16:45:58','TRAJETO_ALTERADO','Trajeto AGORA → São Paulo (GRU) alterado.','trajeto:4'),
(218,'12345678902','2026-11-24 16:46:02','TRAJETO_EXCLUIDO','Trajeto AGORA → São Paulo (GRU) excluído.','trajeto:4'),
(219,'12345678902','2026-11-24 16:46:14','TRAJETO_CRIADO','Trajeto teste → teste1 cadastrado.','trajeto:42'),
(220,'12345678902','2026-11-24 16:46:21','TRAJETO_EXCLUIDO','Trajeto teste → teste1 excluído.','trajeto:42'),
(221,'12345678902','2026-11-24 16:46:34','AERONAVE_EXCLUIDA','Aeronave PT-ABC excluída.','aeronave:1'),
(222,'12345678902','2026-11-24 16:46:51','AERONAVE_ALTERADA','Aeronave PT-DEF alterada.','aeronave:2'),
(223,'12345678902','2026-11-24 16:47:33','AERONAVE_CRIADA','Aeronave JFJBREJGBEJ cadastrada.','aeronave:12'),
(224,'12345678902','2026-11-24 16:47:41','AERONAVE_EXCLUIDA','Aeronave JFJBREJGBEJ excluída.','aeronave:12'),
(225,'12345678902','2026-11-24 16:49:13','TRIPULANTE_CRIADO','Tripulante vkldnrtkljgtnekjn cadastrado como copiloto.','tripulante:11111111111'),
(226,'12345678902','2026-11-24 16:49:18','TRIPULANTE_EXCLUIDO','Tripulante vkldnrtkljgtnekjn excluído.','tripulante:11111111111'),
(227,'12345678903','2026-11-24 16:51:30','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:12345678903'),
(228,'12345678901','2026-11-24 16:56:24','VOO_CRIADO','Voo G7-2501 de 2026-11-25 criado usando os horários do trajeto.','voo:69'),
(229,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000022 alocado ao voo G7-2501 como piloto.','voo:69'),
(230,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000023 alocado ao voo G7-2501 como copiloto.','voo:69'),
(231,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000026 alocado ao voo G7-2501 como comissario.','voo:69'),
(232,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000027 alocado ao voo G7-2501 como comissario.','voo:69'),
(233,'12345678901','2026-11-24 16:56:24','VOO_CRIADO','Voo G7-2502 de 2026-11-25 criado usando os horários do trajeto.','voo:70'),
(234,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000024 alocado ao voo G7-2502 como piloto.','voo:70'),
(235,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000025 alocado ao voo G7-2502 como copiloto.','voo:70'),
(236,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000028 alocado ao voo G7-2502 como comissario.','voo:70'),
(237,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000029 alocado ao voo G7-2502 como comissario.','voo:70'),
(238,'12345678901','2026-11-24 16:56:24','VOO_CRIADO','Voo G7-2503 de 2026-11-25 criado usando os horários do trajeto.','voo:71'),
(239,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000022 alocado ao voo G7-2503 como piloto.','voo:71'),
(240,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000023 alocado ao voo G7-2503 como copiloto.','voo:71'),
(241,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000026 alocado ao voo G7-2503 como comissario.','voo:71'),
(242,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000027 alocado ao voo G7-2503 como comissario.','voo:71'),
(243,'12345678901','2026-11-24 16:56:24','VOO_CRIADO','Voo G7-2601 de 2026-11-26 criado usando os horários do trajeto.','voo:72'),
(244,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000022 alocado ao voo G7-2601 como piloto.','voo:72'),
(245,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000023 alocado ao voo G7-2601 como copiloto.','voo:72'),
(246,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000026 alocado ao voo G7-2601 como comissario.','voo:72'),
(247,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000027 alocado ao voo G7-2601 como comissario.','voo:72'),
(248,'12345678901','2026-11-24 16:56:24','VOO_CRIADO','Voo G7-2602 de 2026-11-26 criado usando os horários do trajeto.','voo:73'),
(249,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000022 alocado ao voo G7-2602 como piloto.','voo:73'),
(250,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000023 alocado ao voo G7-2602 como copiloto.','voo:73'),
(251,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000026 alocado ao voo G7-2602 como comissario.','voo:73'),
(252,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000027 alocado ao voo G7-2602 como comissario.','voo:73'),
(253,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000024 alocado ao voo QWE como piloto.','voo:67'),
(254,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000025 alocado ao voo QWE como copiloto.','voo:67'),
(255,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000028 alocado ao voo QWE como comissario.','voo:67'),
(256,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000029 alocado ao voo QWE como comissario.','voo:67'),
(257,'12345678901','2026-11-24 16:56:24','VOO_CRIADO','Voo G7-2801 de 2026-11-28 criado usando os horários do trajeto.','voo:74'),
(258,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000022 alocado ao voo G7-2801 como piloto.','voo:74'),
(259,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000023 alocado ao voo G7-2801 como copiloto.','voo:74'),
(260,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000026 alocado ao voo G7-2801 como comissario.','voo:74'),
(261,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000027 alocado ao voo G7-2801 como comissario.','voo:74'),
(262,'12345678901','2026-11-24 16:56:24','VOO_CRIADO','Voo G7-2802 de 2026-11-28 criado usando os horários do trajeto.','voo:75'),
(263,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000022 alocado ao voo G7-2802 como piloto.','voo:75'),
(264,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000023 alocado ao voo G7-2802 como copiloto.','voo:75'),
(265,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000026 alocado ao voo G7-2802 como comissario.','voo:75'),
(266,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000027 alocado ao voo G7-2802 como comissario.','voo:75'),
(267,'12345678901','2026-11-24 16:56:24','VOO_CRIADO','Voo G7-2901 de 2026-11-29 criado usando os horários do trajeto.','voo:76'),
(268,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000022 alocado ao voo G7-2901 como piloto.','voo:76'),
(269,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000023 alocado ao voo G7-2901 como copiloto.','voo:76'),
(270,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000026 alocado ao voo G7-2901 como comissario.','voo:76'),
(271,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000027 alocado ao voo G7-2901 como comissario.','voo:76'),
(272,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000024 alocado ao voo JKL como piloto.','voo:68'),
(273,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000025 alocado ao voo JKL como copiloto.','voo:68'),
(274,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000028 alocado ao voo JKL como comissario.','voo:68'),
(275,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000029 alocado ao voo JKL como comissario.','voo:68'),
(276,'12345678901','2026-11-24 16:56:24','VOO_CRIADO','Voo G7-2902 de 2026-11-29 criado usando os horários do trajeto.','voo:77'),
(277,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000022 alocado ao voo G7-2902 como piloto.','voo:77'),
(278,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000023 alocado ao voo G7-2902 como copiloto.','voo:77'),
(279,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000026 alocado ao voo G7-2902 como comissario.','voo:77'),
(280,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000027 alocado ao voo G7-2902 como comissario.','voo:77'),
(281,'12345678901','2026-11-24 16:56:24','VOO_CRIADO','Voo G7-3001 de 2026-11-30 criado usando os horários do trajeto.','voo:78'),
(282,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000024 alocado ao voo G7-3001 como piloto.','voo:78'),
(283,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000025 alocado ao voo G7-3001 como copiloto.','voo:78'),
(284,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000028 alocado ao voo G7-3001 como comissario.','voo:78'),
(285,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000029 alocado ao voo G7-3001 como comissario.','voo:78'),
(286,'12345678901','2026-11-24 16:56:24','VOO_CRIADO','Voo G7-3002 de 2026-11-30 criado usando os horários do trajeto.','voo:79'),
(287,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000024 alocado ao voo G7-3002 como piloto.','voo:79'),
(288,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000025 alocado ao voo G7-3002 como copiloto.','voo:79'),
(289,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000028 alocado ao voo G7-3002 como comissario.','voo:79'),
(290,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000029 alocado ao voo G7-3002 como comissario.','voo:79'),
(291,'12345678901','2026-11-24 16:56:24','VOO_CRIADO','Voo G7-0201 de 2026-12-02 criado usando os horários do trajeto.','voo:80'),
(292,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 12345678903 alocado ao voo G7-0201 como piloto.','voo:80'),
(293,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000022 alocado ao voo G7-0201 como copiloto.','voo:80'),
(294,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000030 alocado ao voo G7-0201 como comissario.','voo:80'),
(295,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000031 alocado ao voo G7-0201 como comissario.','voo:80'),
(296,'12345678901','2026-11-24 16:56:24','VOO_CRIADO','Voo G7-0202 de 2026-12-02 criado usando os horários do trajeto.','voo:81'),
(297,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000024 alocado ao voo G7-0202 como piloto.','voo:81'),
(298,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000025 alocado ao voo G7-0202 como copiloto.','voo:81'),
(299,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000028 alocado ao voo G7-0202 como comissario.','voo:81'),
(300,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000029 alocado ao voo G7-0202 como comissario.','voo:81'),
(301,'12345678901','2026-11-24 16:56:24','VOO_CRIADO','Voo G7-0203 de 2026-12-02 criado usando os horários do trajeto.','voo:82'),
(302,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 12345678903 alocado ao voo G7-0203 como piloto.','voo:82'),
(303,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000022 alocado ao voo G7-0203 como copiloto.','voo:82'),
(304,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000030 alocado ao voo G7-0203 como comissario.','voo:82'),
(305,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000031 alocado ao voo G7-0203 como comissario.','voo:82'),
(306,'12345678901','2026-11-24 16:56:24','VOO_CRIADO','Voo G7-0301 de 2026-12-03 criado usando os horários do trajeto.','voo:83'),
(307,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 12345678903 alocado ao voo G7-0301 como piloto.','voo:83'),
(308,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000022 alocado ao voo G7-0301 como copiloto.','voo:83'),
(309,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000030 alocado ao voo G7-0301 como comissario.','voo:83'),
(310,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000031 alocado ao voo G7-0301 como comissario.','voo:83'),
(311,'12345678901','2026-11-24 16:56:24','VOO_CRIADO','Voo G7-0302 de 2026-12-03 criado usando os horários do trajeto.','voo:84'),
(312,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 12345678903 alocado ao voo G7-0302 como piloto.','voo:84'),
(313,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000022 alocado ao voo G7-0302 como copiloto.','voo:84'),
(314,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000030 alocado ao voo G7-0302 como comissario.','voo:84'),
(315,'12345678901','2026-11-24 16:56:24','ESCALA_CRIADA','Tripulante 00000000031 alocado ao voo G7-0302 como comissario.','voo:84'),
(316,'12345678904','2026-11-24 16:56:44','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(317,'12345678903','2026-11-24 16:56:46','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:12345678903'),
(318,'00000000024','2026-11-24 16:56:47','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:00000000024'),
(319,'00000000022','2026-11-24 16:56:49','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:00000000022'),
(320,'00000000026','2026-11-24 16:56:51','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:00000000026'),
(321,'12345678901','2026-11-24 16:56:53','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(322,'12345678903','2026-11-24 16:58:44','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678903'),
(323,'12345678903','2026-11-24 16:59:40','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:12345678903'),
(324,'12345678901','2026-11-24 17:01:42','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(325,'12345678903','2026-11-24 17:03:40','LOGIN_TRIPULANTE','Login realizado no perfil Tripulante.','usuario:12345678903'),
(326,'12345678901','2026-11-24 17:03:50','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(327,'12345678904','2026-11-24 17:04:54','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(328,'12345678902','2026-11-24 17:04:59','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(329,'12345678902','2026-11-24 17:10:43','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(330,'12345678902','2026-11-24 17:10:44','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(331,'12345678904','2026-11-24 17:10:45','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(332,'12345678901','2026-11-24 17:10:46','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(333,'12345678901','2026-11-24 17:11:52','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(334,'12345678901','2026-11-24 17:13:08','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(335,'12345678901','2026-11-24 17:22:09','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(336,'12345678904','2026-11-24 17:44:39','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(357,'12345678902','2026-11-24 17:55:03','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(358,'12345678901','2026-11-24 17:55:04','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(359,'12345678901','2026-11-24 17:59:42','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(369,'12345678901','2026-11-24 18:04:09','VOO_EXCLUIDO','Voo G7-2501 de 2026-11-25 excluído.','voo:69'),
(370,'12345678901','2026-11-24 18:04:13','VOO_EXCLUIDO','Voo G7-2502 de 2026-11-25 excluído.','voo:70'),
(371,'12345678901','2026-11-24 18:07:30','VOO_CRIADO','Voo TESTE de 2026-12-05 criado usando os horários do trajeto.','voo:89'),
(372,'12345678901','2026-11-24 18:07:50','VOO_ALTERADO','Voo TESTE de 2026-12-05 alterado.','voo:89'),
(373,'12345678901','2026-11-24 18:08:00','VOO_EXCLUIDO','Voo TESTE de 2026-12-05 excluído.','voo:89'),
(374,'12345678902','2026-11-24 18:08:42','LOGIN_GERENTE','Login realizado no perfil Gerente.','usuario:12345678902'),
(375,'12345678904','2026-11-24 18:09:50','LOGIN_CLIENTE','Cliente realizou login.','usuario:12345678904'),
(376,'12345678901','2026-11-24 18:10:33','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(377,'12345678901','2026-11-24 18:12:34','VOO_EXCLUIDO','Voo G7-2503 de 2026-11-25 excluído.','voo:71'),
(378,'12345678901','2026-11-24 18:12:39','VOO_EXCLUIDO','Voo G7-2602 de 2026-11-26 excluído.','voo:73'),
(379,'12345678901','2026-11-24 18:12:49','VOO_EXCLUIDO','Voo G7-2601 de 2026-11-26 excluído.','voo:72'),
(380,'12345678901','2026-11-24 18:13:52','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(381,'12345678901','2026-11-24 18:15:23','LOGIN_ADMIN','Login realizado no perfil Administrador.','usuario:12345678901'),
(382,'12345678901','2026-11-24 18:15:30','VOO_EXCLUIDO','Voo G7-2801 de 2026-11-28 excluído.','voo:74');

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

INSERT INTO `passagem` (`id`, `numero_bilhete`, `data_compra`, `valor`, `status`, `data_cancelamento`, `cliente_cpf`, `voo_id`, `assento_id`, `data_atualizacao`, `versao`, `assento_ativo`) VALUES
(43,'B8DB67F10E','2026-11-24 16:09:09',649.90,'cancelada','2026-11-24 13:45:04','12345678904',67,424,'2026-11-24 16:45:04',2,NULL),
(44,'9186B0762F','2026-11-24 16:44:56',649.90,'confirmada',NULL,'12345678904',67,420,'2026-11-24 16:44:56',1,1);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `trajeto`
--

INSERT INTO `trajeto` (`id`, `origem`, `destino`, `h_partida`, `h_chegada`, `gerente_cpf`) VALUES
(1,'São Paulo (GRU)','Rio de Janeiro (GIG)','08:00:00','10:00:00','12345678902'),
(3,'São Paulo (GRU)','Brasília (BSB)','10:30:00','12:45:00','12345678902'),
(6,'São Paulo (GRU)','São Luís (SLZ)','06:00:00','08:30:00','12345678902'),
(7,'São Luís (SLZ)','São Paulo (GRU)','19:00:00','21:30:00','12345678902'),
(9,'São Paulo (GRU)','Recife (REC)','11:00:00','13:30:00','12345678902'),
(11,'Belo Horizonte (CNF)','São Paulo (GRU)','15:30:00','17:00:00','12345678902'),
(12,'São Paulo (GRU)','Brasília (BSB)','10:30:00','12:45:00','12345678902'),
(13,'Rio de Janeiro (GIG)','São Paulo (GRU)','14:00:00','15:45:00','12345678902'),
(15,'São Paulo (GRU)','São Luís (SLZ)','06:00:00','08:30:00','12345678902'),
(16,'São Luís (SLZ)','São Paulo (GRU)','19:00:00','21:30:00','12345678902'),
(18,'São Paulo (GRU)','Recife (REC)','11:00:00','13:30:00','12345678902'),
(19,'Brasília (BSB)','Belo Horizonte (CNF)','09:00:00','10:30:00','12345678902');

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
('00000000022','piloto'),
('00000000023','piloto'),
('00000000024','piloto'),
('00000000025','piloto'),
('00000000026','comissario'),
('00000000027','comissario'),
('00000000028','comissario'),
('00000000029','comissario'),
('00000000030','comissario'),
('00000000031','comissario'),
('12345678903','piloto');

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
('00000000001','Cliente Teste','cliente@teste.com','$2y$12$8O/arIZ77quJX8X1U7CVoeQo4gKM/dmd4TeaB.jTRJc6d/eemt09W','Rua Teste, 123',NULL),
('00000000021','Leonardo Rocha','piloto.01@decolae.com','$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm','Rua sebastião 13, bairro 22 de outubro','32995487963'),
('00000000022','Sebastião Pedro','piloto.demo02@decolae.com','$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm','Rua sebastião 13, bairro 22 de outubro','11988887777'),
('00000000023','Neymar Junior','piloto.3@decolae.com','$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm','Endereço de demonstração',NULL),
('00000000024','Vinicius Junior','piloto.4@decolae.com','$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm','Endereço de demonstração',NULL),
('00000000025','Pedro Arrascaeta','piloto.5@decolae.com','$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm','Endereço de demonstração',NULL),
('00000000026','Rossi Golger','comissario.demo01@decolae.com','$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm','Endereço de demonstração',NULL),
('00000000027','Luiz Guilherme','comissario.demo02@decolae.com','$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm','Endereço de demonstração',NULL),
('00000000028','Nivaldo Andrade','comissario.demo03@decolae.com','$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm','Endereço de demonstração',NULL),
('00000000029','Luiz Inácio','comissario.demo04@decolae.com','$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm','Endereço de demonstração',NULL),
('00000000030','Virginia Fonseca','comissario.demo05@decolae.com','$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm','Endereço de demonstração',NULL),
('00000000031','Paulo Fernando Neves','comissario.6@decolae.com','$2y$12$bWrWN60tDowkts.X6afWeeOnPO5.qO1KhNsC3NreoWkVmCKQBMrTm','Endereço de demonstração',NULL),
('10005767601','ricardo marcio mamede','ricardo@ricardo.com','$2y$12$DelASUVgy42JSXLMSbidy.tt6XexVIaWAcShmf7MklTM9ZlZADRke','','32998312505'),
('10005767604','paulo','paulo@paulao.com','$2y$12$/PCsH01j/13jqZoKLBdrGe5VaMIC3PWxUK7M3bpXQM0dsEbvTwtMq','','32998312508'),
('12345678901','Admin Silva','admin@decolae.com','$2y$12$4qo2Zxs6rSKCeHsEsexuOem6tqePndmb6f03R96YZATXZprPL2FkS','Rua Principal 100',NULL),
('12345678902','Gerente Costa','gerente@decolae.com','$2y$12$2nIObKXtWcLOLMt64XD8SeB7igA2GbRFYefvFilKMSEo5./mdTU1q','Avenida Central 200',NULL),
('12345678903','Tripulante Santos','tripulante@decolae.com','$2y$12$yKIYmhiMJlHbPd8i5Q7rPuZPGaoJVFP1YjmvoHhqdfRWoOpzbiyue','Rua Lateral 300',NULL),
('12345678904','Cliente Oliveira','cliente@decolae.com','$2y$12$d2AoEViwhM0L4K8BAZx1A.nz1rlwe1pnhiE0sTPt58zZ1UE5x1y2C','Rua Cliente 400','11999999999'),
('14434473616','Ana Cláudia Machado','anamachadohp@gmail.com','$2y$12$wbGEGYQyqpxipukihQxS.ex1ZsMOK5IZ3V8L6wjlFlVxdHq5drHda','','32998140779'),
('14434573616','Ana Cláudia Machado','anaclaudiamachado211@gmail.com','$2y$12$qi845kzLNge41iDD341riOV/FrIraalFUa0k6dQq5G79QXRVpDj/.','','32999215547'),
('14785236945','Jão Pedro Santana','joao@decolae.com','$2y$12$QXz5k4C17sUTXYxLhDYUiu9TkhKDbr6MorBk9a6Wbs0vwr9qOAygC','Rua Brasilia, 184','32990857667');

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
(67,'QWE','2026-11-27',19,8,'12345678901',649.90,'programado','2026-11-24 14:43:26',1),
(68,'JKL','2026-11-29',11,8,'12345678901',649.90,'programado','2026-11-24 14:44:05',1),
(75,'G7-2802','2026-11-28',13,2,'12345678901',329.90,'programado','2026-11-24 16:56:24',1),
(76,'G7-2901','2026-11-29',6,2,'12345678901',499.90,'programado','2026-11-24 16:56:24',1),
(77,'G7-2902','2026-11-29',7,2,'12345678901',479.90,'programado','2026-11-24 16:56:24',1),
(78,'G7-3001','2026-11-30',1,8,'12345678901',349.90,'programado','2026-11-24 16:56:24',1),
(79,'G7-3002','2026-11-30',13,8,'12345678901',329.90,'programado','2026-11-24 16:56:24',1),
(80,'G7-0201','2026-12-02',6,2,'12345678901',499.90,'programado','2026-11-24 16:56:24',1),
(81,'G7-0202','2026-12-02',9,8,'12345678901',459.90,'programado','2026-11-24 16:56:24',1),
(82,'G7-0203','2026-12-02',7,2,'12345678901',479.90,'programado','2026-11-24 16:56:24',1),
(83,'G7-0301','2026-12-03',1,2,'12345678901',349.90,'programado','2026-11-24 16:56:24',1),
(84,'G7-0302','2026-12-03',13,2,'12345678901',329.90,'programado','2026-11-24 16:56:24',1);

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
,`chegada_em` datetime
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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=900;

--
-- AUTO_INCREMENT de tabela `auditoria_tabela`
--
ALTER TABLE `auditoria_tabela`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT de tabela `escala`
--
ALTER TABLE `escala`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=153;

--
-- AUTO_INCREMENT de tabela `log`
--
ALTER TABLE `log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=383;

--
-- AUTO_INCREMENT de tabela `passagem`
--
ALTER TABLE `passagem`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT de tabela `trajeto`
--
ALTER TABLE `trajeto`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `voo`
--
ALTER TABLE `voo`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_voos_disponibilidade`
--
DROP TABLE IF EXISTS `vw_voos_disponibilidade`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_voos_disponibilidade`  AS SELECT `v`.`id` AS `id`, `v`.`numero_voo` AS `numero_voo`, `v`.`data_voo` AS `data_voo`, `v`.`status` AS `status`, `v`.`valor_base` AS `valor_base`, `t`.`origem` AS `origem`, `t`.`destino` AS `destino`, `t`.`h_partida` AS `h_partida`, `t`.`h_chegada` AS `h_chegada`, concat(`v`.`data_voo`,' ',`t`.`h_partida`) AS `partida_em`, timestamp(`v`.`data_voo`,`t`.`h_chegada`) + interval (`t`.`h_chegada` < `t`.`h_partida`) day AS `chegada_em`, `a`.`modelo` AS `modelo`, `a`.`identificacao` AS `identificacao`, `a`.`fileiras`* `a`.`assentos_por_fileira` AS `capacidade`, coalesce(`a`.`fileiras` * `a`.`assentos_por_fileira` - (select count(0) from `passagem` where `passagem`.`voo_id` = `v`.`id` and `passagem`.`status` in ('confirmada','checkin')),`a`.`fileiras` * `a`.`assentos_por_fileira`) AS `assentos_livres` FROM ((`voo` `v` join `trajeto` `t` on(`t`.`id` = `v`.`trajeto_id`)) join `aeronave` `a` on(`a`.`id` = `v`.`aeronave_id`)) ;

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

