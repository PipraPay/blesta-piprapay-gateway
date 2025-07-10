# PipraPay Blesta Integration

The **PipraPay Checkout** module allows you to effortlessly integrate PipraPay's payment gateway with the [Blesta](https://www.blesta.com) billing and automation platform.

## 🚀 Features

- Seamless integration with Blesta
- Easy API configuration
- Support for Bangladeshi payment methods

---

## 📥 Installation

Follow these steps to install the PipraPay module in your Blesta installation:

1. **Upload** the `PipraPay.zip` file to your Blesta installation directory.
2. **Extract** the contents of the ZIP file to the appropriate module directory:  
   ```
   /path-to-blesta/components/gateways/nonmerchant/
   ```
3. After extraction, ensure the module folder name is `piprapay`.

---

## ⚙️ Gateway Activation

To activate and configure the PipraPay gateway:

1. Go to your Blesta admin panel:  
   **Blesta > Settings > Payment Gateways > Available**
2. Find **BD Payment Methods** or **PipraPay** from the list.
3. Click on **Install**.
4. Enter your **API KEY** , **API URL** and **Currency Code** provided by PipraPay.
5. Click **Update Settings** to save.

---

## 🛠️ Configuration Fields

- **API URL**: Your and **API URL** endpoint URL  
  *(e.g., `https://pay.yourdomain.com/api`)*
- **API KEY**: Your PipraPay secret API key from dashboard

---

## 📚 Support

For support or inquiries, please contact [PipraPay](https://piprapay.com) or open an issue in this repository.

---

## 📄 License

This project is open-source and free to use under the MIT License.
