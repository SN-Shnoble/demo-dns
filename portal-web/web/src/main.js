import { createApp } from 'vue'
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'
import {
    Aim,
    ArrowDown,
    Avatar,
    CaretRight,
    Check,
    CircleCheck,
    Coin,
    Collection,
    Connection,
    CopyDocument,
    CreditCard,
    DataAnalysis,
    Delete,
    Document,
    Download,
    Hide,
    Iphone,
    Key,
    Link,
    List,
    Lock,
    Message,
    Monitor,
    Money,
    Plus,
    Refresh,
    RefreshLeft,
    Remove,
    Search,
    Setting,
    SwitchButton,
    Tickets,
    Tools,
    Upload,
    User,
    UserFilled,
    Wallet,
} from '@element-plus/icons-vue'
import './assets/theme.css'
import App from './App.vue'
import router from './router'
import i18n from './locales'

const app = createApp(App)

const icons = {
    Aim,
    ArrowDown,
    Avatar,
    CaretRight,
    Check,
    CircleCheck,
    Coin,
    Collection,
    Connection,
    CopyDocument,
    CreditCard,
    DataAnalysis,
    Delete,
    Document,
    Download,
    Hide,
    Iphone,
    Key,
    Link,
    List,
    Lock,
    Message,
    Monitor,
    Money,
    Plus,
    Refresh,
    RefreshLeft,
    Remove,
    Search,
    Setting,
    SwitchButton,
    Tickets,
    Tools,
    Upload,
    User,
    UserFilled,
    Wallet,
}

for (const [key, component] of Object.entries(icons)) {
    app.component(key, component)
}

app.use(ElementPlus)
app.use(router)
app.use(i18n)
app.mount('#app')
