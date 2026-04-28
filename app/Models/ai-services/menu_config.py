from pathlib import Path


BASE_DIR = Path(__file__).resolve().parent
DATASET_DIRNAME = "dataset_jus_food_14class"

MENU_LIST = [
    {
        "folder": "jus_alpukat",
        "keywords": [
            "jus alpukat coklat",
            "avocado juice indonesian",
            "jus alpukat kental",
            "avocado chocolate drink",
        ],
    },
    {
        "folder": "jus_buah_naga",
        "keywords": [
            "jus buah naga merah",
            "dragon fruit juice pink glass",
            "fresh dragon fruit smoothie",
            "jus buah naga segar",
        ],
    },
    {
        "folder": "jus_jambu",
        "keywords": [
            "jus jambu merah indonesia",
            "guava juice pink glass",
            "jus jambu biji segar",
            "fresh guava juice drink",
        ],
    },
    {
        "folder": "jus_mangga",
        "keywords": [
            "jus mangga segar",
            "mango juice fresh glass",
            "jus mangga dingin",
            "fresh mango smoothie",
        ],
    },
    {
        "folder": "jus_melon",
        "keywords": [
            "jus melon hijau segar",
            "melon juice green glass",
            "jus melon dingin",
            "fresh melon juice drink",
        ],
    },
    {
        "folder": "jus_nanas",
        "keywords": [
            "jus nanas segar",
            "pineapple juice yellow glass",
            "jus nanas dingin",
            "fresh pineapple juice drink",
        ],
    },
    {
        "folder": "jus_semangka",
        "keywords": [
            "jus semangka segar",
            "watermelon juice red glass",
            "jus semangka dingin",
            "fresh watermelon juice drink",
        ],
    },
    {
        "folder": "jus_sirsak",
        "keywords": [
            "jus sirsak putih kental",
            "soursop juice white glass",
            "jus sirsak segar indonesia",
            "fresh soursop juice drink",
        ],
    },
    {
        "folder": "jus_tomat",
        "keywords": [
            "jus tomat merah segar",
            "tomato juice red glass",
            "jus tomat indonesia",
            "fresh tomato juice drink",
        ],
    },
    {
        "folder": "jus_wortel",
        "keywords": [
            "jus wortel segar",
            "carrot juice orange glass",
            "jus wortel indonesia",
            "fresh carrot juice drink",
        ],
    },
    {
        "folder": "roti_bakar_coklat",
        "keywords": [
            "roti bakar coklat indonesia",
            "chocolate toast indonesian street food",
            "roti bakar meses coklat",
            "grilled bread chocolate filling",
        ],
    },
    {
        "folder": "roti_bakar_keju",
        "keywords": [
            "roti bakar keju indonesia",
            "cheese toast indonesian street food",
            "roti bakar keju parut",
            "grilled bread cheese filling",
        ],
    },
    {
        "folder": "salad_sayur",
        "keywords": [
            "salad sayur segar bowl",
            "fresh vegetable salad plate",
            "indonesian vegetable salad",
            "green salad lunch bowl",
        ],
    },
    {
        "folder": "salad_sayur_telur",
        "keywords": [
            "salad sayur telur bowl",
            "vegetable salad with egg",
            "fresh salad boiled egg plate",
            "green salad egg topping",
        ],
    },
]

CLASS_NAMES = [menu["folder"] for menu in MENU_LIST]
