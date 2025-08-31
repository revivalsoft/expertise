# 🏡 Outil d'Estimation de Terrains

## 📌 Description  
Cet outil est une application **Symfony 7** conçue pour **aider à l’estimation de la valeur d’un terrain** en se basant sur des données comparatives.  
Il propose plusieurs **méthodes de calcul statistiques et analytiques** pour fournir une estimation fiable à partir d’un fichier **CSV** contenant :  
- **Prix** des terrains environnants  
- **Surface** des terrains comparables  


L’objectif est d’obtenir une **fourchette de prix estimative** pour un terrain donné, à partir des données du marché local.

---

## ✅ Fonctionnalités  
- 📂 **Importation de fichiers CSV** avec la liste des terrains comparables  
- 📊 **Méthodes de calcul par régression** pour l’estimation :  
  - Linéaire  
  - Logarithmique
  - Puissance
  - Lowess  
- 🔍 **Analyse rapide** des données (prix moyen, prix/m²)  
- 🖥 **Interface web simple et ergonomique** développée avec **Symfony 7**  
 

---

## 🛠 Prérequis  
- **PHP 8.3+**  
- **Symfony 7**  
- **Composer**  

---

## 🚀 Installation (exemple)

1. **Cloner le dépôt**  
```bash
git clone https://github.com/revivalsoft/expertise.git
cd expertise
composer install
