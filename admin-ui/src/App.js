import React from "react";
import NavbarContentContainer from "./components/containers/NavbarContentContainer";
import {postRequest} from "./components/Common/postRequest";
import LoadingAnimation from "./components/Common/LoadingAnimation";
import {CommonContext, UiLabelContext} from "./Context";
import {alertifyToast, getJSONData, isValidJSON} from "./helpers/utilities";

const App = () => {

    const [appState, setAppState] = React.useState({})
    const [labels, setLabels] = React.useState({})
    const [loading, setLoading] = React.useState(true);

    const [commonState, setCommonState] = React.useState({});
    React.useLayoutEffect(() => {
        const params = {
            action: "wlmi_admin_local_data",
            wlmi_nonce: wlmi_settings_form.local_data_nonce,
        }
        postRequest(params).then((json) => {
            let resJSON = getJSONData(json.data);
            if (resJSON.success === true && resJSON.data !== null && resJSON.data !== {}) {
                setAppState(resJSON.data);
                let labelParams = {
                    action: "wlmi_get_labels",
                    wlmi_nonce: resJSON.data.common_nonce,
                }
                postRequest(labelParams).then(json => {
                    let labelsJSON = getJSONData(json.data);
                    if (resJSON !== {}) setLabels(labelsJSON.data);
                    setLoading(false);
                })
            } else if (resJSON.success === false && resJSON.data !== null) {
                alertifyToast(resJSON.message, false);
                setLoading(false);
            }
        });
    }, []);

    return (<div
        className={"wlmi-bg-grey_extra_light wlmi-px-5 xl:wlmi-px-10 wlmi-py-5 wlmi-h-full wlmi-w-full wlmi-flex wlmi-items-start wlmi-justify-start wlmi-flex-col"}
        style={{
            fontFamily: `Helvetica`
        }}
    >
        {/* Heading Title and version*/}
        {loading ? <LoadingAnimation height={"wlmi-h-[85vh]"}/>
            : (
                <div className={` wlmi-w-full `}>
                    <div className={`wlmi-flex wlmi-items-baseline  wlmi-gap-x-3`}>
                        <p className={`wlmi-text-2xl wlmi-text-dark wlmi-font-bold`}>{labels?.common?.plugin_name || ""}</p>
                        <span className={`wlmi-text-base wlmi-text-extra_light wlmi-font-medium`}>{labels?.common?.version || ""}</span>
                    </div>
                    <CommonContext.Provider value={
                        {
                            commonState, setCommonState,
                            appState, setAppState
                        }
                    }>
                        <UiLabelContext.Provider value={labels}>
                            <NavbarContentContainer/>
                        </UiLabelContext.Provider>
                    </CommonContext.Provider>
                </div>)
        }
    </div>)
};
export default App;
