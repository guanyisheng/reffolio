# Reffolio

<p align="center">
  <img src="reffolio/docs/banner.png" alt="Reffolio" width="720">
</p>

<p align="center">
  <strong>角色设定与稿件管理系统</strong> — PHP / MySQL，支持本地存储与腾讯云 COS
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-green.svg" alt="MIT"></a>
  <a href="reffolio/README.md"><img src="https://img.shields.io/badge/PHP-8%2B-777BB4?logo=php&logoColor=white" alt="PHP"></a>
</p>

---

本仓库为开源项目，**应用代码在 [`reffolio/`](reffolio/) 目录**。

## 目录结构

```
reffolio/          ← 仓库根目录
├── README.md      ← 本文件
├── LICENSE
└── reffolio/      ← PHP 应用（Web 根目录指向此处）
    ├── index.php
    ├── assets/
    ├── config/
    ├── includes/
    ├── sql/
    └── ...
```

## 快速开始

```bash
git clone https://github.com/guanyisheng/reffolio.git
cd reffolio/reffolio
cp config/database.example.php config/database.php
# 编辑 config/database.php 后导入 sql/database.sql
php -S localhost:8080
```

完整文档见 **[reffolio/README.md](reffolio/README.md)**。

## License

[MIT](LICENSE)
