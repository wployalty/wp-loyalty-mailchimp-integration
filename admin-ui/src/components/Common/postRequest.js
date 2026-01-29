import axios from "axios";

export const postRequest = (params, url = wlmi_settings_form.ajax_url) => {

    let headers = {"Content-Type": "multipart/form-data",};
    let data = new FormData();
    params.is_admin_side = true;
    if (params) {
        Object.keys(params).map((key) => {
            data.append(key, params[key])
        });
    }
    return axios({
        method: "POST",
        url,
        data,
        headers,
    })
};